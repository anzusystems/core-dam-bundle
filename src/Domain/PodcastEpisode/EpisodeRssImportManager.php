<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\App;
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
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\AudioFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAudioTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\PodcastEpisode\PodcastEpisodeImportDto;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
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
        private ImageFileRepository $imageFileRepository,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws DomainException
     */
    public function importEpisode(Podcast $podcast, Item $podcastItem): PodcastEpisodeImportDto
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

            $slot = $this->getTargetSlot($episode);
            // Episode has Asset but import slot is empty
            if (null === $slot) {
                return $this->assignToPodcastEpisodeAndExistingAsset($episode, $asset, $podcastItem);
            }

            // Slot is used but URL equals (already imported episode)
            if ($slot->getAssetFile()->getAssetAttributes()->getOriginUrl() === $podcastItem->getEnclosure()->getUrl()) {
                return new PodcastEpisodeImportDto(
                    episode: $episode,
                    newlyImported: false
                );
            }
            // Probably new version of podcast was uploaded, need to solve manually
            $this->podcastEpisodeStatusManager->toConflict($episode);

            return new PodcastEpisodeImportDto(
                episode: $episode,
                newlyImported: false
            );
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

            throw new DomainException(
                sprintf('Podcast episode (%s) import failed', $podcastItem->getTitle())
            );
        }
    }

    /**
     * Returns AssetSlot assigned to episode and specified in Podcast
     */
    public function getTargetSlot(PodcastEpisode $episode): ?AssetSlot
    {
        $asset = $episode->getAsset();
        if (null === $asset) {
            return null;
        }

        $slotName = $this->getTargetSlotName($episode);
        $slot = $asset->getSlots()->filter(
            fn (AssetSlot $assetSlot): bool => $assetSlot->getName() === $slotName
        )->first();

        return $slot instanceof AssetSlot ? $slot : null;
    }

    private function getTargetSlotName(PodcastEpisode $episode): string
    {
        $configuration = $this->configurationProvider->getExtSystemConfigurationByAssetType(
            assetType: AssetType::Audio,
            extSystemSlug: $episode->getExtSystem()->getSlug()
        );
        $requiredSlotName = $episode->getPodcast()->getAttributes()->getFileSlot();

        return empty($requiredSlotName) ? $configuration->getSlots()->getDefault() : $requiredSlotName;
    }

    /**
     * @throws SerializerException
     */
    private function createPodcastEpisode(Podcast $podcast, Item $item): PodcastEpisodeImportDto
    {
        $audioFile = $this->prepareAudioFile(podcast: $podcast, item: $item);
        $episode = $this->podcastEpisodeFactory->createEpisodeWithAsset($audioFile->getAsset(), $podcast, false);

        $this->updateImage($episode, $item);
        $this->updateEpisodeData($episode, $item, $audioFile);
        $this->toUploaded($audioFile);

        return new PodcastEpisodeImportDto(
            episode: $episode,
            newlyImported: true
        );
    }

    /**
     * @throws SerializerException
     */
    private function assignToPodcastEpisode(PodcastEpisode $episode, Item $item): PodcastEpisodeImportDto
    {
        $audioFile = $this->prepareAudioFile(podcast: $episode->getPodcast(), item: $item);
        $audioFile->getAsset()->addEpisode($episode);

        $this->updateImage($episode, $item);
        $this->updateEpisodeData($episode, $item, $audioFile);
        $this->toUploaded($audioFile);

        return new PodcastEpisodeImportDto(
            episode: $episode,
            newlyImported: true
        );
    }

    /**
     * @throws SerializerException
     */
    private function assignToPodcastEpisodeAndExistingAsset(PodcastEpisode $episode, Asset $asset, Item $item): PodcastEpisodeImportDto
    {
        $audioFile = $this->audioFactory->createFromUrl(
            licence: $episode->getPodcast()->getLicence(),
            url: $item->getEnclosure()->getUrl()
        );
        $this->assetSlotFactory->createRelation(
            asset: $asset,
            assetFile: $audioFile,
            slotName: $this->getTargetSlotName($episode),
            flush: false
        );
        $this->audioManager->create($audioFile, false);

        $this->updateImage($episode, $item);
        $this->updateEpisodeData($episode, $item, $audioFile);
        $this->toUploaded($audioFile);

        return new PodcastEpisodeImportDto(
            episode: $episode,
            newlyImported: true
        );
    }

    private function updateEpisodeData(PodcastEpisode $episode, Item $item, AudioFile $audioFile): void
    {
        $episode->getTexts()
            ->setTitle($item->getTitle())
            ->setDescription(StringHelper::parseString($item->getDescription()))
            ->setRawDescription($item->getDescription())
        ;
        $episode->getDates()->setPublicationDate($item->getPubDate());
        $duration = $item->getItunes()->getDurationInSeconds();
        $episode->getAttributes()
            ->setRssId($item->getGuid())
            ->setRssUrl($item->getEnclosure()->getUrl())
            ->setDuration(
                $duration > App::ZERO ? $duration : $audioFile->getAttributes()->getDuration()
            )
        ;
        $episode->getFlags()->setFromRss(true);
        $this->podcastEpisodeStatusManager->toImported($episode, false);
    }

    /**
     * @throws SerializerException
     */
    private function updateImage(PodcastEpisode $podcastEpisode, Item $item): void
    {
        if ($podcastEpisode->getImagePreview()) {
            return;
        }

        if (empty($item->getItunes()->getImage())) {
            return;
        }

        $imageFile = $this->imageDownloadFacade->downloadSynchronous(
            assetLicence: $podcastEpisode->getPodcast()->getLicence(),
            url: $item->getItunes()->getImage()
        );

        if ($imageFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Duplicate)) {
            $imageFile = $this->imageFileRepository->find($imageFile->getAssetAttributes()->getOriginAssetId());
        }

        if (null === $imageFile) {
            return;
        }

        if ($imageFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_PODCAST_RSS_IMPORT,
                sprintf('ImageFile download failed (%s)', $item->getItunes()->getImage())
            );

            return;
        }

        $podcastEpisode->setImagePreview(
            $this->imagePreviewFactory->createFromImageFile(
                imageFile: $imageFile,
                flush: false
            )
        );
    }

    private function prepareAudioFile(Podcast $podcast, Item $item): AudioFile
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
    private function toUploaded(AudioFile $audioFile): void
    {
        $this->assetFileStatusManager->toUploaded($audioFile);
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($audioFile);
        $this->messageBus->dispatch(new AudioFileChangeStateMessage($audioFile));
    }
}
