<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Facade;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\PodcastLicenceFilter;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Replace the full set of podcast memberships for a TTS asset (PUT semantics).
 * Licence-mismatched podcasts are logged + skipped — sync never aborts the request.
 */
final readonly class TtsPodcastMembershipFacade
{
    public function __construct(
        private TtsAssetLocker $ttsAssetLocker,
        private PodcastEpisodeManager $episodeManager,
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
        private PodcastLicenceFilter $podcastLicenceFilter,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param Collection<int, Podcast> $podcasts
     *
     * @throws RegenCancelledException
     */
    public function execute(Asset $asset, Collection $podcasts): void
    {
        App::throwOnReadOnlyMode();

        $desired = $this->podcastLicenceFilter->filter($asset, $podcasts);

        $this->entityManager->wrapInTransaction(function () use ($asset, $desired): void {
            $this->ttsAssetLocker->requireFor($asset);
            $this->episodeManager->setMembership($asset, $desired);
        });

        // Propagate the membership change to linked ext-systems (e.g. the CMS medium's episode/podcast) —
        // mirrors the creation paths in TtsRequestOrchestrator, which the standalone change path was missing.
        $this->extSystemCallbackFacade->notifyAssetsChanged(new ArrayCollection([$asset]));
    }
}
