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
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\CoreDamBundle\Messenger\Message\AudioFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
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
        private readonly ImageDownloadFacade $imageDownloadFacade,
        private readonly ImagePreviewFactory $imagePreviewFactory,
        private readonly PodcastEpisodeRepository $podcastEpisodeRepository,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function importEpisode(Podcast $podcast, Item $podcastItem): PodcastEpisode
    {
        $episode = $this->podcastEpisodeRepository->findOneTitleAndPodcast($podcastItem->getTitle(), $podcast);

        if (null === $episode) {
            return $this->createPodcastEpisode($podcast, $podcastItem);
        }
        if (null === $episode->getAsset()) {
            return $this->assignToPodcastEpisode($episode, $podcastItem);
        }

        return $this->replaceAssetAtEpisode($episode, $podcastItem);
    }

    private function replaceAssetAtEpisode(PodcastEpisode $podcastEpisode, Item $item): PodcastEpisode
    {
        return $podcastEpisode;
    }

    /**
     * @throws SerializerException
     */
    private function createPodcastEpisode(Podcast $podcast, Item $item): PodcastEpisode
    {
        $assetFile = $this->downloadAsset(
            licence: $podcast->getLicence(),
            item: $item
        );

        $imageFileForPreview = $this->getPreviewImage($podcast->getLicence(), $item);
        $episode = $this->podcastEpisodeFactory->createEpisodeWithAsset($assetFile->getAsset(), $podcast, false);
        $episode->getTexts()->setTitle($item->getTitle());
        $this->updatePodcastEpisode($episode, $item);

        if ($imageFileForPreview) {
            $episode->setImagePreview(
                $this->imagePreviewFactory->createFromImageFile(
                    imageFile: $imageFileForPreview,
                    flush: false
                )
            );
        }

        $this->toUploaded($assetFile);

        return $episode;
    }

    /**
     * @throws SerializerException
     */
    private function assignToPodcastEpisode(PodcastEpisode $podcastEpisode, Item $item): PodcastEpisode
    {
        $assetFile = $this->downloadAsset(
            licence: $podcastEpisode->getPodcast()->getLicence(),
            item: $item
        );

        $this->updatePodcastEpisode($podcastEpisode, $item);
        $podcastEpisode->setAsset($assetFile->getAsset());
        $assetFile->getAsset()->getEpisodes()->add($podcastEpisode);
        $this->toUploaded($assetFile);

        return $podcastEpisode;
    }

    private function updatePodcastEpisode(PodcastEpisode $episode, Item $item): void
    {
        $episode->getAttributes()
            ->setRssId($item->getGuid())
            ->setRssUrl($item->getEnclosure()->getUrl())
        ;
    }

    private function downloadAsset(AssetLicence $licence, Item $item): AudioFile
    {
        // @todo downloader
        $audioFile = $this->audioFactory->createFromUrl(
            licence: $licence,
            url: $item->getEnclosure()->getUrl()
        );
        $asset = $this->assetFactory->createForAssetFile(
            assetFile: $audioFile,
            assetLicence: $licence
        );
        $asset->getAssetFlags()->setDescribed(true);
        $this->audioManager->create($audioFile, false);

        $this->textsWriter->writeValues(
            from: $item,
            to: $asset,
            config: $this->configurationProvider->getExtSystemConfigurationByAsset($asset)->getPodcastEpisodeRssMap()
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
