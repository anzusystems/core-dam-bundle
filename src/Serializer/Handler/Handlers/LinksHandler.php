<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Cache\AssetFileRouteGenerator;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\CropAllowItem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\Traits\AdminImageLinksTrait;
use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Symfony\Component\HttpFoundation\RequestStack;

class LinksHandler extends AbstractHandler
{
    use AdminImageLinksTrait;

    public const string IMAGE_TAG_LIST = 'list';
    public const string IMAGE_TAG_DETAIL = 'detail';
    public const string IMAGE_TAG_TABLE = 'table';
    public const string IMAGE_TAG_ROI_EXAMPLE = 'roi_example';

    public const string IMAGE_KEY_ANIMATED = 'image_animated';
    public const string AUDIO_KEY_AUDIO = 'audio';

    protected const string IMAGE_LINKS_TYPE = 'image';
    protected const string AUDIO_LINKS_TYPE = 'audio';

    protected const array IMAGE_TAGS = [
        self::IMAGE_TAG_LIST,
        self::IMAGE_TAG_DETAIL,
        self::IMAGE_TAG_TABLE,
        self::IMAGE_TAG_ROI_EXAMPLE,
    ];

    private const int LINKS_TAGS_LIMIT = 5;
    private const string LINKS_QUERY_PARAM = '_links';

    public function __construct(
        protected readonly ConfigurationProvider $configurationProvider,
        protected readonly ImageUrlFactory $imageUrlFactory,
        private readonly RequestStack $requestStack,
        private readonly AssetFileRouteGenerator $audioRouteGenerator,
        private readonly AssetFileRouteRepository $assetFileRouteRepository,
    ) {
    }

    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): mixed
    {
        if ($value instanceof ImageFile) {
            return $this->getImageFileLinks($value, $metadata);
        }
        if ($value instanceof AudioFile) {
            $imagePreview = $this->getImagePreview($value);
            if ($imagePreview) {
                return $this->getImageFileLinks($imagePreview, $metadata);
            }
        }

        return [];
    }

    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    protected function getImageFileLinks(ImageFile $imageFile, Metadata $metadata): array
    {
        if ($imageFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            return [];
        }

        $res = [];
        $tags = is_string($metadata->customType) ? [$metadata->customType] : $this->getTagsFromRequest(self::IMAGE_TAGS);
        foreach ($tags as $tag) {
            $sizeList = $this->getTaggedList($imageFile, $tag);

            if (empty($sizeList)) {
                continue;
            }

            $res[$this->getKey($tag)] = $this->serializeImageCrop($imageFile, $sizeList[array_key_first($sizeList)]);
        }

        if ($imageFile->getImageAttributes()->isAnimated()) {
            $width = $imageFile->getImageAttributes()->getWidth();
            $height = $imageFile->getImageAttributes()->getHeight();

            $res[self::IMAGE_KEY_ANIMATED] = $this->serializeLinksData(
                type: self::IMAGE_LINKS_TYPE,
                url: $this->getDomain($imageFile) . $this->imageUrlFactory->generateAnimatedUrl((string) $imageFile->getId()),
                requestedWidth: $width,
                requestedHeight: $imageFile->getImageAttributes()->getHeight(),
                title: "{$width}x{$height}",
            );
        }

        return $res;
    }

    protected function serializeImageCrop(ImageFile $imageFile, CropAllowItem $item): string|array
    {
        $imageId = (string) $imageFile->getId();

        return $this->serializeLinksData(
            type: self::IMAGE_LINKS_TYPE,
            url: $this->getDomain($imageFile) . $this->imageUrlFactory->generateAllowListUrl(
                imageId: $imageId,
                item: $item,
                roiPosition: 0
            ),
            requestedWidth: $item->getWidth(),
            requestedHeight: $item->getHeight(),
            title: empty($item->getTitle())
                ? "{$item->getWidth()}x{$item->getHeight()}"
                : $item->getTitle(),
        );
    }

    protected function serializeLinksData(
        string $type,
        string $url,
        int $requestedWidth,
        int $requestedHeight,
        string $title,
    ): string|array {
        return [
            'type' => $type,
            'url' => $url,
            'requestedWidth' => $requestedWidth,
            'requestedHeight' => $requestedHeight,
            'title' => $title,
        ];
    }

    protected function getKey(string $tag): string
    {
        return self::IMAGE_LINKS_TYPE . '_' . $tag;
    }

    protected function getTagsFromRequest(array $allowList): array
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if (null === $mainRequest) {
            return $allowList;
        }

        $links = $mainRequest->query->get(self::LINKS_QUERY_PARAM);
        if (false === is_string($links) || empty($links)) {
            return $allowList;
        }

        $links = array_map(
            fn (string $link): string => trim($link),
            explode(',', $links, self::LINKS_TAGS_LIMIT)
        );

        return array_intersect(
            $links,
            $allowList
        );
    }

    private function getAudioFileLinks(AudioFile $audioFile): array
    {
        $route = $this->assetFileRouteRepository->findMainByAssetFile((string) $audioFile->getId());

        return $route
            ? [self::AUDIO_KEY_AUDIO => $this->serializeAudioPublicLink($route)]
            : []
        ;
    }

    private function serializeAudioPublicLink(AssetFileRoute $assetFileRoute): array
    {
        return [
            'type' => self::AUDIO_LINKS_TYPE,
            'url' => $this->audioRouteGenerator->getFullUrl($assetFileRoute),
        ];
    }

    private function getImagePreview(AssetFile $assetFile): ?ImageFile
    {
        $firstEpisode = $assetFile->getAsset()->getEpisodes()->first();

        if ($firstEpisode instanceof PodcastEpisode) {
            return $firstEpisode->getImagePreview()?->getImageFile() ?? $firstEpisode->getPodcast()->getImagePreview()?->getImageFile();
        }

        return null;
    }
}
