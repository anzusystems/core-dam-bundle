<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use Doctrine\Common\Collections\Collection;

final class PodcastEpisodeFactory extends AbstractManager
{
    public function __construct(
        private readonly PodcastEpisodeManager $manager,
        private readonly PodcastEpisodeRepository $repository,
        private readonly AssetTextsWriter $assetTextsWriter,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly TtsAssetRepository $ttsAssetRepository,
        private readonly ImageFileRepository $imageFileRepository,
        private readonly ImagePreviewFactory $imagePreviewFactory,
    ) {
    }

    public function createEpisodeWithAsset(
        Asset $asset,
        Podcast $podcast,
        bool $flush = true,
        bool $inheritFromAsset = false,
    ): PodcastEpisode {
        return $this->createEpisode(
            asset: $asset,
            podcast: $podcast,
            flush: $flush,
            inheritFromAsset: $inheritFromAsset,
            inheritedImage: $inheritFromAsset ? $this->resolveInheritedImageFile($asset) : null,
        );
    }

    /**
     * @param Collection<int, Podcast> $desiredPodcasts
     */
    public function setMembership(Asset $asset, Collection $desiredPodcasts, bool $flush = true, bool $inheritFromAsset = false): void
    {
        // Manual diff by podcastId: colUpdate assumes a homogeneous Collection.
        $currentByPodcastId = [];
        foreach ($asset->getEpisodes() as $episode) {
            $currentByPodcastId[(string) $episode->getPodcast()->getId()] = $episode;
        }

        $desiredByPodcastId = [];
        foreach ($desiredPodcasts as $podcast) {
            $desiredByPodcastId[(string) $podcast->getId()] = $podcast;
        }

        $inheritedImage = $inheritFromAsset ? $this->resolveInheritedImageFile($asset) : null;

        foreach (array_diff_key($desiredByPodcastId, $currentByPodcastId) as $podcast) {
            $this->createEpisode(
                asset: $asset,
                podcast: $podcast,
                flush: false,
                inheritFromAsset: $inheritFromAsset,
                inheritedImage: $inheritedImage,
            );
        }

        foreach (array_diff_key($currentByPodcastId, $desiredByPodcastId) as $episode) {
            $asset->removeEpisode($episode);
            $this->manager->delete($episode, false);
        }

        $this->flush($flush);
    }

    public function reconcileEpisodesFromAsset(Asset $asset, bool $flush = true): void
    {
        $inheritedImage = $this->resolveInheritedImageFile($asset);

        foreach ($asset->getEpisodes() as $episode) {
            if ($this->seedFromAsset($episode, $asset, $inheritedImage)) {
                $this->manager->updateExisting($episode, flush: false);
            }
        }

        $this->flush($flush);
    }

    private function createEpisode(
        Asset $asset,
        Podcast $podcast,
        bool $flush,
        bool $inheritFromAsset,
        ?ImageFile $inheritedImage,
    ): PodcastEpisode {
        // addEpisode() keeps the in-memory episode collection in sync for event consumers.
        $podcastEpisode = (new PodcastEpisode())->setPodcast($podcast);
        $asset->addEpisode($podcastEpisode);
        if ($inheritFromAsset) {
            $this->applyAssetDefaults($podcastEpisode, $asset, $inheritedImage);
        }

        return $this->manager->create($podcastEpisode, $flush);
    }

    private function applyAssetDefaults(PodcastEpisode $episode, Asset $asset, ?ImageFile $inheritedImage): void
    {
        $config = $this->extSystemConfigurationProvider->getAudioExtSystemConfiguration(
            $asset->getLicence()->getExtSystem()->getSlug()
        );
        $this->assetTextsWriter->writeValues($asset, $episode, $config->getPodcastEpisodeEntityMap());

        $lastEpisode = $this->repository->findOneLastByPodcast($episode->getPodcast());
        $episode->getAttributes()->setEpisodeNumber(($lastEpisode?->getAttributes()->getEpisodeNumber() ?? App::ZERO) + 1);

        $this->seedFromAsset($episode, $asset, $inheritedImage);
    }

    private function seedFromAsset(PodcastEpisode $episode, Asset $asset, ?ImageFile $inheritedImage): bool
    {
        $changed = false;

        $duration = $this->resolveAudioDuration($asset);
        if (App::ZERO !== $duration && App::ZERO === $episode->getAttributes()->getDuration()) {
            $episode->getAttributes()->setDuration($duration);
            $changed = true;
        }
        if (null !== $inheritedImage && null === $episode->getImagePreview()) {
            $episode->setImagePreview($this->imagePreviewFactory->createFromImageFile(imageFile: $inheritedImage, flush: false));
            $changed = true;
        }

        return $changed;
    }

    private function resolveAudioDuration(Asset $asset): int
    {
        $mainFile = $asset->getMainFile();

        return $mainFile instanceof AudioFile ? $mainFile->getAttributes()->getDuration() : App::ZERO;
    }

    private function resolveInheritedImageFile(Asset $asset): ?ImageFile
    {
        $imageFileId = $this->ttsAssetRepository->findByAsset($asset)?->getMainImageFileId();

        return null === $imageFileId
            ? null
            : $this->imageFileRepository->findProcessedByIdAndLicence($imageFileId, $asset->getLicence());
    }
}
