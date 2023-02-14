<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\AssetExternalProvider\Provider;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\AssetExternalProviderApiParams;
use AnzuSystems\CoreDamBundle\AssetExternalProvider\ConfigResolver\UnsplashConfigResolver;
use AnzuSystems\CoreDamBundle\HttpClient\UnsplashClient;
use AnzuSystems\CoreDamBundle\Model\Configuration\UnsplashAssetExternalProviderConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\AssetExternalProviderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Embeds\AssetExternalProviderAttributesDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Embeds\AssetExternalProviderTextsDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash\UnsplashImageDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash\UnsplashMetadataDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;

final class UnsplashAssetExternalProvider implements AssetExternalProviderInterface
{
    private UnsplashAssetExternalProviderConfiguration $configuration;

    public function __construct(
        private readonly UnsplashClient $unsplashClient,
    ) {
    }

    public function setConfiguration(array $config): void
    {
        $this->configuration = (new UnsplashConfigResolver())->resolve($config);
    }

    public function getConfiguration(): UnsplashAssetExternalProviderConfiguration
    {
        return $this->configuration;
    }

    /**
     * @return ApiInfiniteResponseList<AssetExternalProviderDto>
     *
     * @throws CacheException
     * @throws SerializerException
     * @throws InvalidArgumentException
     */
    public function search(AssetExternalProviderApiParams $apiParams): ApiInfiniteResponseList
    {
        $list = $this->unsplashClient->searchPhotos($this->configuration, $apiParams);
        /** @var ApiInfiniteResponseList<AssetExternalProviderDto> $response */
        $response = new ApiInfiniteResponseList();

        return $response
            ->setData(array_map($this->mapUnsplashImageDtoToListDto(...), $list->getData()))
            ->setHasNextPage($list->isHasNextPage())
        ;
    }

    /**
     * @return ApiResponseList<AssetExternalProviderDto>
     *
     * @throws CacheException
     * @throws SerializerException
     * @throws InvalidArgumentException
     */
    public function getByIds(array $ids): ApiResponseList
    {
        /** @var ApiResponseList<AssetExternalProviderDto> $list */
        $list = new ApiResponseList();
        $images = $this->unsplashClient->getPhotosByIds($this->configuration, $ids);

        return $list
            ->setData(array_map($this->mapUnsplashImageDtoToDetailDto(...), $images))
            ->setTotalCount(count($images))
        ;
    }

    /**
     * @throws SerializerException
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function getById(string $id): AssetExternalProviderDto
    {
        /** @var UnsplashImageDto $image */
        $image = $this->unsplashClient->getPhotoById($this->configuration, $id);

        return $this->mapUnsplashImageDtoToDetailDto($image);
    }

    /**
     * @return resource
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    public function download(string $id)
    {
        return $this->unsplashClient->download($this->configuration, $id);
    }

    private function mapUnsplashImageDtoToListDto(UnsplashImageDto $imageDto): AssetExternalProviderDto
    {
        return $this->mapUnsplashImageDto($imageDto, $imageDto->getUrls()->getSmall());
    }

    private function mapUnsplashImageDtoToDetailDto(UnsplashImageDto $imageDto): AssetExternalProviderDto
    {
        return $this->mapUnsplashImageDto($imageDto, $imageDto->getUrls()->getFull());
    }

    private function mapUnsplashImageDto(UnsplashImageDto $imageDto, string $imageUrl): AssetExternalProviderDto
    {
        return AssetExternalProviderDto::getInstance(
            id: $imageDto->getId(),
            url: $imageUrl,
            attributes: AssetExternalProviderAttributesDto::getInstance(AssetType::Image),
            texts: AssetExternalProviderTextsDto::getInstance(
                displayTitle: $imageDto->getDisplayTitle(),
                description: $imageDto->getResolvedDescription(),
            ),
            metadata: UnsplashMetadataDto::getInstance($imageDto),
        );
    }
}
