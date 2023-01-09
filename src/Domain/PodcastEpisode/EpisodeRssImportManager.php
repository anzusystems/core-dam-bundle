<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusManager;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\CoreDamBundle\Messenger\Message\AudioFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use Symfony\Component\Messenger\MessageBusInterface;

final class EpisodeRssImportManager
{
    public function __construct(
        private readonly AudioFactory $audioFactory,
        private readonly AssetFactory $assetFactory,
        private readonly AudioManager $audioManager,
        private readonly AssetFileStatusManager $assetFileStatusManager,
        private readonly MessageBusInterface $messageBus,
        private readonly AssetFileEventDispatcher $assetFileEventDispatcher,
        private readonly PodcastEpisodeFactory $podcastEpisodeFactory,
        private readonly AssetTextsWriter $textsWriter,
        private readonly ExtSystemConfigurationProvider $configurationProvider,
        private readonly PodcastEpisodeManager $episodeManager,
        private readonly ImageDownloadFacade $imageDownloadFacade,
    ) {
    }

    public function createAsset(Podcast $podcast, Item $item): void
    {
        // todo downloader
        $audioFile = $this->audioFactory->createFromUrl($podcast->getLicence(), $item->getEnclosure()->getUrl());
        $asset = $this->assetFactory->createForAssetFile($audioFile, $podcast->getLicence());
        $this->audioManager->create($audioFile, false);

        $episode = $this->podcastEpisodeFactory->assignAssetToPodcast($asset, $podcast, false);
        $episode->getTexts()->setTitle($item->getTitle());

        $this->textsWriter->writeValues(
            from: $item,
            to: $asset,
            config: $this->configurationProvider->getExtSystemConfigurationByAsset($asset)->getPodcastEpisodeRssMap()
        );

        $this->assetFileStatusManager->toUploaded($audioFile);
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($audioFile);
        $this->messageBus->dispatch(new AudioFileChangeStateMessage($audioFile));

        if (false === empty($item->getItunes()->getImage())) {
            $episode->setPreviewImage(
                $this->imageDownloadFacade->download(
                    assetLicence: $podcast->getLicence(),
                    url: $item->getItunes()->getImage()
                )
            );
            $this->episodeManager->flush();
        }
    }
}
