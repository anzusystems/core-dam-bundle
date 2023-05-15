<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionDtoFactory;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;

final class JwVideoImagePreviewFactory extends AbstractDistributionDtoFactory
{
    public const DISTRIBUTION_CROP_TAG = 'jw_distribution';

    public function __construct(
        protected ConfigurationProvider $configurationProvider,
        protected ImageUrlFactory $imageUrlFactory,
    ) {
    }

    public function getThumbnailUrl(AssetFile $assetFile): ?string
    {
        if ($assetFile instanceof AudioFile) {
            return $this->getAudioPreviewUrl($assetFile);
        }

        return null;
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

        return $this->generateUrl((string) $imagePreview->getImageFile()->getId());
    }

    private function generateUrl(string $imageId): ?string
    {
        $cropAllowItem = $this->configurationProvider->getFirstTaggedAllowItem(self::DISTRIBUTION_CROP_TAG);

        if (null === $cropAllowItem) {
            return null;
        }

        return $this->configurationProvider->getAdminDomain() . $this->imageUrlFactory->generatePublicUrl(
            imageId: $imageId,
            width: $cropAllowItem->getWidth(),
            height: $cropAllowItem->getHeight(),
        );
    }
}
