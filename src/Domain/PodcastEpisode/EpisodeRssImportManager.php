<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\AudioFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAudioTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

final readonly class EpisodeRssImportManager
{
    public function __construct(
        private AudioFactory $audioFactory,
        private AssetFactory $assetFactory,
        private AudioManager $audioManager,
        private AssetFileStatusManager $assetFileStatusManager,
        private MessageBusInterface $messageBus,
        private AssetFileEventDispatcher $assetFileEventDispatcher,
        private PodcastEpisodeFactory $podcastEpisodeFactory,
        private AssetTextsWriter $textsWriter,
        private ExtSystemConfigurationProvider $configurationProvider,
        private ImageDownloadFacade $imageDownloadFacade,
        private ImagePreviewFactory $imagePreviewFactory,
        private PodcastEpisodeRepository $podcastEpisodeRepository,
        private PodcastEpisodeStatusManager $podcastEpisodeStatusManager,
        private AssetSlotFactory $assetSlotFactory,
        private DamLogger $damLogger,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function importEpisode(Podcast $podcast, Item $podcastItem): bool
    {
        $episode = null;

        try {
            $episode = $this->podcastEpisodeRepository->findOneTitleAndPodcast($podcastItem->getTitle(), $podcast);
            // Episode not exists, create
            if (null === $episode) {
                return $this->createPodcastEpisode($podcast, $podcastItem);
            }

            $asset = $episode->getAsset();
            // Episode exists, but has no asset
            if (null === $asset) {
                return $this->assignToPodcastEpisode($episode, $podcastItem);
            }

            $slot = $episode->getTargetSlot();
            // Episode has Asset but import slot is empty
            if (null === $slot) {
                return $this->assignToPodcastEpisodeAndExistingAsset($episode, $asset, $podcastItem);
            }

            // Slot is used but URL equals (already imported episode)
            if ($slot->getAssetFile()->getAssetAttributes()->getOriginUrl() === $podcastItem->getEnclosure()->getUrl()) {
                return false;
            }

            // Probably new version of podcast was uploaded, need to solve manually
            $this->podcastEpisodeStatusManager->toConflict($episode);

            return false;
        } catch (Throwable $exception) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_PODCAST_RSS_IMPORT,
                sprintf(
                    'Podcast episode (%s) import failed (%s)',
                    $podcastItem->getTitle(),
                    $exception->getMessage()
                )
            );

            if ($episode) {
                $this->podcastEpisodeStatusManager->toImportFailed($episode);
            }
        }

        return false;
    }

    /**
     * @throws SerializerException
     */
    private function createPodcastEpisode(Podcast $podcast, Item $item): bool
    {
        $audioFile = $this->downloadAsset(
            podcast: $podcast,
            item: $item
        );

        $episode = $this->podcastEpisodeFactory->createEpisodeWithAsset($audioFile->getAsset(), $podcast, false);

        $this->updateEpisodeData($episode, $item);
        $this->toUploaded($audioFile);

        return true;
    }

    /**
     * @throws SerializerException
     */
    private function assignToPodcastEpisode(PodcastEpisode $podcastEpisode, Item $item): bool
    {
        $audioFile = $this->downloadAsset(
            podcast: $podcastEpisode->getPodcast(),
            item: $item
        );
        $audioFile->getAsset()->addEpisode($podcastEpisode);

        $this->updateEpisodeData($podcastEpisode, $item);
        $this->toUploaded($audioFile);

        return true;
    }

    /**
     * @throws SerializerException
     */
    private function assignToPodcastEpisodeAndExistingAsset(PodcastEpisode $podcastEpisode, Asset $asset, Item $item): bool
    {
        $audioFile = $this->audioFactory->createFromUrl(
            licence: $podcastEpisode->getPodcast()->getLicence(),
            url: $item->getEnclosure()->getUrl()
        );
        $this->assetSlotFactory->createRelation(
            asset: $asset,
            assetFile: $audioFile,
            slotName: $podcastEpisode->getPodcast()->getAttributes()->getFileSlot()
        );

        $this->updateEpisodeData($podcastEpisode, $item);
        $this->toUploaded($audioFile);

        return true;
    }

    /**
     * @throws SerializerException
     */
    private function updateEpisodeData(PodcastEpisode $podcastEpisode, Item $item): void
    {
        $podcastEpisode->getTexts()->setTitle($item->getTitle());

        $imageFileForPreview = $this->getPreviewImage($podcastEpisode->getPodcast()->getLicence(), $item);
        if ($imageFileForPreview) {
            $podcastEpisode->setImagePreview(
                $this->imagePreviewFactory->createFromImageFile(
                    imageFile: $imageFileForPreview,
                    flush: false
                )
            );
        }

        $this->updatePodcastEpisodeAttributes($podcastEpisode, $item);
        $this->podcastEpisodeStatusManager->toImported($podcastEpisode, false);
    }

    /**
     * Assigns Attributes from imported RssItem
     */
    private function updatePodcastEpisodeAttributes(PodcastEpisode $episode, Item $item): void
    {
        $episode->getAttributes()
            ->setRssId($item->getGuid())
            ->setRssUrl($item->getEnclosure()->getUrl());
        $episode->getFlags()->setFromRss(true);
    }

    private function downloadAsset(Podcast $podcast, Item $item): AudioFile
    {
        $audioFile = $this->audioFactory->createFromUrl(
            licence: $podcast->getLicence(),
            url: $item->getEnclosure()->getUrl()
        );
        $asset = $this->assetFactory->createForAssetFile(
            assetFile: $audioFile,
            assetLicence: $podcast->getLicence(),
            slotName: $podcast->getAttributes()->getFileSlot()
        );
        $asset->getAssetFlags()->setDescribed(true);
        $this->audioManager->create($audioFile, false);

        $config = $this->configurationProvider->getExtSystemConfigurationByAsset($asset);
        if (false === ($config instanceof ExtSystemAudioTypeConfiguration)) {
            throw new InvalidArgumentException('Asset type must be a type of audio');
        }

        $this->textsWriter->writeValues(
            from: $item,
            to: $asset,
            config: $config->getPodcastEpisodeRssMap()
        );

        return $audioFile;
    }

    /**
     * @throws SerializerException
     */
    private function getPreviewImage(AssetLicence $licence, Item $item): ?ImageFile
    {
        if (empty($item->getItunes()->getImage())) {
            return null;
        }

        return $this->imageDownloadFacade->download(
            assetLicence: $licence,
            url: $item->getItunes()->getImage()
        );
    }

    /**
     * @throws SerializerException
     */
    private function toUploaded(AudioFile $audioFile): void
    {
        $this->assetFileStatusManager->toUploaded($audioFile);
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($audioFile);
        $this->messageBus->dispatch(new AudioFileChangeStateMessage($audioFile));
    }
}
