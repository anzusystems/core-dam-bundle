<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusManager;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetFileChangeStateMessage;
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
    ) {
    }

    public function createAsset(Podcast $podcast, Item $item): void
    {
        $audioFile = $this->audioFactory->createFromRssItem($podcast->getLicence(), $item);
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
        $this->messageBus->dispatch(new AssetFileChangeStateMessage($audioFile));
    }
}
