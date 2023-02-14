<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\VideoShowEpisode;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use AnzuSystems\CoreDamBundle\Entity\VideoShowEpisode;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemVideoTypeConfiguration;
use InvalidArgumentException;

final class VideoShowEpisodeBodyFacade
{
    public function __construct(
        private readonly VideoShowEpisodeManager $videoShowEpisodeManager,
        private readonly AssetTextsWriter $assetTextsWriter,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
    ) {
    }

    public function preparePayload(Asset $asset, VideoShow $videoShow): VideoShowEpisode
    {
        $episode = (new VideoShowEpisode())
            ->setVideoShow($videoShow)
            ->setAsset($asset);

        $this->videoShowEpisodeManager->trackModification($episode);
        $this->videoShowEpisodeManager->trackCreation($episode);

        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAsset($asset);
        if (false === ($config instanceof ExtSystemVideoTypeConfiguration)) {
            throw new InvalidArgumentException('Asset type must be a type of video');
        }

        $this->assetTextsWriter->writeValues(
            from: $asset,
            to: $episode,
            config: $config->getVideoEpisodeEntityMap()
        );

        return $episode;
    }
}
