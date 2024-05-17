<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Cache\AssetFileRouteGenerator;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\CropAllowItem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Symfony\Component\HttpFoundation\RequestStack;

class LinksHandler extends AbstractHandler
{
    public const IMAGE_TAG_LIST = 'list';
    public const IMAGE_TAG_DETAIL = 'detail';
    public const IMAGE_TAG_TABLE = 'table';
    public const IMAGE_TAG_ROI_EXAMPLE = 'roi_example';

    public const IMAGE_KEY_ANIMATED = 'image_animated';
    public const AUDIO_KEY_AUDIO = 'audio';

    protected const IMAGE_LINKS_TYPE = 'image';
    protected const AUDIO_LINKS_TYPE = 'audio';

    protected const IMAGE_TAGS = [
        self::IMAGE_TAG_LIST,
        self::IMAGE_TAG_DETAIL,
        self::IMAGE_TAG_TABLE,
        self::IMAGE_TAG_ROI_EXAMPLE,
    ];

    private const int LINKS_TAGS_LIMIT = 5;
    private const string LINKS_QUERY_PARAM = '_links';

    public function __construct(
        protected readonly ConfigurationProvider $configurationProvider,
        protected readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        protected readonly ImageUrlFactory $imageUrlFactory,
        private readonly RequestStack $requestStack,
        private readonly AssetFileRouteGenerator $audioRouteGenerator,
        private readonly AssetFileRouteRepository $assetFileRouteRepository,
    ) {
    }

    public function serialize(mixed $value, Metadata $metadata): mixed
    {
        if ($value instanceof ImageFile) {
            return $this->getImageFileLinks($value);
        }
        if ($value instanceof AudioFile) {
            $imagePreview = $this->getImagePreview($value);
            if ($imagePreview) {
                return [
                    ...$this->getImageFileLinks($imagePreview),
                    ...$this->getAudioFileLinks($value),
                ];
            }

            return $this->getAudioFileLinks($value);
        }

        return null;
    }

    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    protected function getImageFileLinks(ImageFile $imageFile): array
    {
        if ($imageFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            return [];
        }

        $res = [];
        foreach ($this->getTagsFromRequest(self::IMAGE_TAGS) as $tag) {
            $sizeList = $this->configurationProvider->getImageAdminSizeList($tag);

            if (empty($sizeList)) {
                continue;
            }

            $res[$this->getKey($tag)] = $this->serializeImageCrop($imageFile, $sizeList[array_key_first($sizeList)]);
        }

        if ($imageFile->getImageAttributes()->isAnimated()) {
            $config = $this->extSystemConfigurationProvider->getImageExtSystemConfiguration($imageFile->getExtSystem()->getSlug());
            $width = $imageFile->getImageAttributes()->getWidth();
            $height = $imageFile->getImageAttributes()->getHeight();

            $res[self::IMAGE_KEY_ANIMATED] = [
                'type' => self::IMAGE_LINKS_TYPE,
                'url' => $config->getAdminDomain() . $this->imageUrlFactory->generateAnimatedUrl((string) $imageFile->getId()),
                'requestedWidth' => $width,
                'requestedHeight' => $imageFile->getImageAttributes()->getHeight(),
                'title' => "{$width}x{$height}",
            ];
        }

        return $res;
    }

    protected function serializeImageCrop(ImageFile $imageFile, CropAllowItem $item): array
    {
        $imageId = (string) $imageFile->getId();
        $config = $this->extSystemConfigurationProvider->getImageExtSystemConfiguration($imageFile->getExtSystem()->getSlug());

        return [
            'type' => self::IMAGE_LINKS_TYPE,
            'url' => $config->getAdminDomain() . $this->imageUrlFactory->generateAllowListUrl(
                imageId: $imageId,
                item: $item,
                roiPosition: 0
            ),
            'requestedWidth' => $item->getWidth(),
            'requestedHeight' => $item->getHeight(),
            'title' => empty($item->getTitle())
                ? "{$item->getWidth()}x{$item->getHeight()}"
                : $item->getTitle(),
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
