<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Replace the full set of podcast memberships for an asset (PUT semantics). Licence-mismatched podcasts are
 * logged + skipped — sync never aborts. Linked ext-systems (e.g. the CMS medium) are notified afterwards.
 */
final readonly class PodcastMembershipFacade
{
    public function __construct(
        private PodcastEpisodeManager $episodeManager,
        private PodcastLicenceFilter $podcastLicenceFilter,
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param Collection<int, Podcast> $podcasts
     */
    public function setMembership(Asset $asset, Collection $podcasts): void
    {
        App::throwOnReadOnlyMode();

        $desired = $this->podcastLicenceFilter->filter($asset, $podcasts);

        $this->entityManager->wrapInTransaction(function () use ($asset, $desired): void {
            $this->episodeManager->setMembership($asset, $desired);
        });

        $this->extSystemCallbackFacade->notifyAssetChanged($asset);
    }
}
