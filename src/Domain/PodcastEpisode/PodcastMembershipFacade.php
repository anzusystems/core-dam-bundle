<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetChangedEventDispatcher;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

/** PUT-semantics podcast membership sync; licence-mismatched podcasts are skipped. */
final readonly class PodcastMembershipFacade
{
    public function __construct(
        private PodcastEpisodeFactory $episodeFactory,
        private PodcastLicenceFilter $podcastLicenceFilter,
        private AssetChangedEventDispatcher $assetChangedEventDispatcher,
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
            $this->episodeFactory->setMembership($asset, $desired, inheritFromAsset: true);
        });

        $this->assetChangedEventDispatcher->dispatchAssetChangedEvent(new ArrayCollection([$asset]));
    }
}
