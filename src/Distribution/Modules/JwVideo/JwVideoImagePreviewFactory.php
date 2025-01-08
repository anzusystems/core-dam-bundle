<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\JwVideo;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionDtoFactory;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImagePreview;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;

final class JwVideoImagePreviewFactory extends AbstractDistributionDtoFactory
{
    public function __construct(
        protected ConfigurationProvider $configurationProvider,
        protected ImageUrlFactory $imageUrlFactory,
        protected readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider
    ) {
    }

    public function getImagePreview(AssetFile $assetFile): ?ImagePreview
    {
        if ($assetFile instanceof AudioFile) {
            return $this->getAudioImagePreview($assetFile);
        }
        if ($assetFile instanceof VideoFile) {
            return $assetFile->getImagePreview();
        }

        return null;
    }

    private function getAudioImagePreview(AudioFile $audioFile): ?ImagePreview
    {
        $episode = $audioFile->getAsset()->getEpisodes()->first();
        if (false === ($episode instanceof PodcastEpisode)) {
            return null;
        }

        return $episode->getPodcast()->getAltImage() ?? $episode->getPodcast()->getImagePreview();
    }
}
