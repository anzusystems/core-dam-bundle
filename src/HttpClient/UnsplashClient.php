<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\HttpClient;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\ApiFilter\AssetExternalProviderApiParams;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Configuration\UnsplashAssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash\CachedUnsplashListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash\UnsplashImageDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash\UnsplashListDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class UnsplashClient
{
    use SerializerAwareTrait;

    private const CACHE_TAG = 'unsplash';
    private const SEARCH_CACHE_KEY_PREFIX = 'unsplash_search_';
    private const PHOTO_CACHE_KEY_PREFIX = 'unsplash_photo_';

    public function __construct(
        private readonly HttpClientInterface $unsplashApiClient,
        private readonly CacheItemPoolInterface $coreDamBundleAssetExternalProviderCache,
        private readonly DamLogger $logger,
    ) {
    }

    /**
     * @return ApiInfiniteResponseList<UnsplashImageDto>
     *
     * @throws InvalidArgumentException
     * @throws SerializerException|CacheException
     * @throws CacheException
     */
    public function searchPhotos(
        UnsplashAssetExternalProviderConfiguration $configuration,
        AssetExternalProviderApiParams $apiParams,
    ): ApiInfiniteResponseList {
        $cacheListItem = $this->coreDamBundleAssetExternalProviderCache->getItem(self::getSearchCacheKey($apiParams));
        if ($cacheListItem->isHit()) {
            /** @var CachedUnsplashListDto $cachedList */
            $cachedList = $cacheListItem->get();

            return (new ApiInfiniteResponseList())
                ->setData($this->getPhotosByIds($configuration, $cachedList->getIds()))
                ->setHasNextPage($cachedList->isHasNextPage())
            ;
        }

        $page = 0 === $apiParams->getOffset() ? 1 : ($apiParams->getOffset() / $apiParams->getLimit() + 1);

        try {
            $response = $this->unsplashApiClient->request(
                Request::METHOD_GET,
                '/search/photos',
                [
                    'query' => [
                        'query' => $apiParams->getTerm(),
                        'page' => $page,
                        'per_page' => $apiParams->getLimit(),
                    ],
                ]
                + self::getHeaders($configuration),
            );

            $content = $this->serializer->deserialize($response->getContent(), UnsplashListDto::class);
        } catch (Throwable $exception) {
            $this->logger->error(
                DamLogger::NAMESPACE_ASSET_EXTERNAL_PROVIDER,
                sprintf('Unsplash failed to search (%s)', $exception->getMessage())
            );

            throw new RuntimeException(message: $exception->getMessage(), previous: $exception);
        }

        return $this->cachedResponseList(
            cacheListItem: $cacheListItem,
            listDto: (new ApiInfiniteResponseList())
                ->setData($content->getResults()->getValues())
                ->setHasNextPage($content->getTotalPages() > $page),
        );
    }

    /**
     * @return list<UnsplashImageDto>
     *
     * @throws InvalidArgumentException
     * @throws SerializerException
     * @throws CacheException
     */
    public function getPhotosByIds(UnsplashAssetExternalProviderConfiguration $configuration, array $ids): array
    {
        $out = [];
        $cacheKeys = array_map(static fn (string $id): string => self::getPhotoCacheKey($id), $ids);

        /** @var CacheItemInterface $item */
        foreach ($this->coreDamBundleAssetExternalProviderCache->getItems($cacheKeys) as $item) {
            if ($item->isHit()) {
                $out[] = $item->get();

                continue;
            }

            $photoId = (new UnicodeString($item->getKey()))->after(self::PHOTO_CACHE_KEY_PREFIX);

            try {
                $response = $this->unsplashApiClient->request(
                    Request::METHOD_GET,
                    sprintf('/photos/%s', $photoId),
                    self::getHeaders($configuration),
                );

                $content = $this->serializer->deserialize($response->getContent(), UnsplashImageDto::class);
            } catch (Throwable $exception) {
                $this->logger->error(
                    DamLogger::NAMESPACE_ASSET_EXTERNAL_PROVIDER,
                    sprintf('Unsplash failed to fetch image "%s" (%s)', $photoId, $exception->getMessage())
                );

                continue;
            }

            $out[] = $content;
            $this->coreDamBundleAssetExternalProviderCache->saveDeferred($item->set($content));
        }

        $this->coreDamBundleAssetExternalProviderCache->commit();

        return $out;
    }

    /**
     * @throws CacheException
     * @throws SerializerException
     * @throws InvalidArgumentException
     */
    public function getPhotoById(UnsplashAssetExternalProviderConfiguration $configuration, string $id): ?UnsplashImageDto
    {
        $photo = $this->getPhotosByIds($configuration, [$id])[0] ?? null;
        if (null === $photo) {
            throw new NotFoundHttpException(sprintf('Photo by id (%s) not found.', $id));
        }

        return $this->getPhotosByIds($configuration, [$id])[0] ?? null;
    }

    /**
     * @return resource
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    public function download(UnsplashAssetExternalProviderConfiguration $configuration, string $id)
    {
        $image = $this->getPhotoById($configuration, $id);

        try {
            $response = $this->unsplashApiClient->request(Request::METHOD_GET, $image->getUrls()->getFull());

            return StreamWrapper::createResource($response, $this->unsplashApiClient);
        } catch (Throwable $exception) {
            $this->logger->error(
                DamLogger::NAMESPACE_ASSET_EXTERNAL_PROVIDER,
                sprintf('Unsplash failed to stream image "%s" (%s)', $id, $exception->getMessage())
            );

            throw new RuntimeException(message: $exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    private function cachedResponseList(
        CacheItemInterface $cacheListItem,
        ApiInfiniteResponseList $listDto,
    ): ApiInfiniteResponseList {
        $ids = [];
        foreach ($listDto->getData() as $photo) {
            $cachePhotoItem = $this->coreDamBundleAssetExternalProviderCache->getItem(self::getPhotoCacheKey($photo->getId()));
            $cachePhotoItem->set($photo);
            $ids[] = $photo->getId();
            $this->coreDamBundleAssetExternalProviderCache->saveDeferred($cachePhotoItem);
        }
        $cacheListItem->set(
            (new CachedUnsplashListDto())
                ->setHasNextPage($listDto->isHasNextPage())
                ->setIds($ids)
        );
        $this->coreDamBundleAssetExternalProviderCache->saveDeferred($cacheListItem);

        return $listDto;
    }

    private static function getSearchCacheKey(AssetExternalProviderApiParams $apiParams): string
    {
        return sprintf(
            '%s%s_%d_%d',
            self::SEARCH_CACHE_KEY_PREFIX,
            md5($apiParams->getTerm()),
            $apiParams->getOffset(),
            $apiParams->getLimit()
        );
    }

    private static function getPhotoCacheKey(string $id): string
    {
        return sprintf('%s%s', self::PHOTO_CACHE_KEY_PREFIX, $id);
    }

    private static function getHeaders(UnsplashAssetExternalProviderConfiguration $configuration): array
    {
        return [
            'headers' => [
                'Authorization' => sprintf('Client-ID %s', $configuration->getAccessKey()),
            ],
        ];
    }
}
