<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/** Keeps only podcasts in the asset's ext system; logs the rest. */
final readonly class PodcastLicenceFilter
{
    public function __construct(
        private DamLogger $logger,
    ) {
    }

    /**
     * @param iterable<Podcast> $podcasts
     *
     * @return Collection<int, Podcast>
     */
    public function filter(Asset $asset, iterable $podcasts): Collection
    {
        $desired = new ArrayCollection();
        foreach ($podcasts as $podcast) {
            if ($podcast->getExtSystem()->is($asset->getExtSystem())) {
                $desired->add($podcast);

                continue;
            }

            $this->logger->warning(DamLogger::NAMESPACE_PODCAST_MEMBERSHIP, 'podcastMembership.extSystemMismatch', [
                'podcastId' => (string) $podcast->getId(),
                'assetId' => (string) $asset->getId(),
                'podcastExtSystemId' => (string) $podcast->getExtSystem()->getId(),
                'assetExtSystemId' => (string) $asset->getExtSystem()->getId(),
            ]);
        }

        return $desired;
    }
}
