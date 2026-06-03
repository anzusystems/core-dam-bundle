<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Keeps only the podcasts whose licence matches the asset's licence; licence-mismatched podcasts are
 * logged and skipped so membership sync never aborts. Shared by the podcast-membership facade and the
 * TTS orchestrator (initial/regen creation).
 */
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
            if ($podcast->getLicence()->is($asset->getLicence())) {
                $desired->add($podcast);

                continue;
            }

            $this->logger->warning(DamLogger::NAMESPACE_PODCAST_MEMBERSHIP, 'podcastMembership.licenceMismatch', [
                'podcastId' => (string) $podcast->getId(),
                'assetId' => (string) $asset->getId(),
                'podcastLicenceId' => (string) $podcast->getLicence()->getId(),
                'assetLicenceId' => (string) $asset->getLicence()->getId(),
            ]);
        }

        return $desired;
    }
}
