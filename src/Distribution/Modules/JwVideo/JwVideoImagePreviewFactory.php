<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionDtoFactory;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;

final class JwVideoImagePreviewFactory extends AbstractDistributionDtoFactory
{
    public const string DISTRIBUTION_CROP_TAG = 'jw_distribution';

    public function __construct(
        protected ConfigurationProvider $configurationProvider,
        protected ImageUrlFactory $imageUrlFactory,
        protected readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider
    ) {
    }

    public function getThumbnailUrl(AssetFile $assetFile): ?string
    {
        if ($assetFile instanceof AudioFile) {
            return $this->getAudioPreviewUrl($assetFile);
        }
        if ($assetFile instanceof VideoFile) {
            return $this->getVideoPreviewUrl($assetFile);
        }

        return null;
    }

    private function getVideoPreviewUrl(VideoFile $assetFile): ?string
    {
        $imagePreview = $assetFile->getImagePreview();
        if (null === $imagePreview) {
            return null;
        }

        return $this->generateUrl($imagePreview->getImageFile());
    }

    private function getAudioPreviewUrl(AudioFile $audioFile): ?string
    {
        $episode = $audioFile->getAsset()->getEpisodes()->first();
        if (false === ($episode instanceof PodcastEpisode)) {
            return null;
        }

        $imagePreview =
            $episode->getPodcast()->getAltImage() ??
            $episode->getPodcast()->getImagePreview();

        if (null === $imagePreview) {
            return null;
        }

        return $this->generateUrl($imagePreview->getImageFile());
    }

    private function generateUrl(ImageFile $imageFile): ?string
    {
        $cropAllowItem = $this->configurationProvider->getFirstTaggedAllowItem(self::DISTRIBUTION_CROP_TAG);

        if (null === $cropAllowItem) {
            return null;
        }
        $config = $this->extSystemConfigurationProvider->getImageExtSystemConfiguration($imageFile->getExtSystem()->getSlug());

        return $config->getAdminDomain() . $this->imageUrlFactory->generatePublicUrl(
            imageId: (string) $imageFile->getId(),
            width: $cropAllowItem->getWidth(),
            height: $cropAllowItem->getHeight(),
        );
    }
}
