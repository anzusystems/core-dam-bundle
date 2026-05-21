<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;

/**
 * Idempotent TTS podcast membership sync. Misconfigs (missing podcast, licence mismatch) are logged
 * + skipped — sync must never abort the TTS pipeline.
 */
final readonly class PodcastMembership
{
    public function __construct(
        private PodcastEpisodeManager $episodeManager,
        private PodcastEpisodeRepository $episodeRepo,
        private PodcastRepository $podcastRepo,
        private DamLogger $logger,
    ) {
    }

    public function syncAutoPodcast(Asset $asset, string $autoPodcastId): void
    {
        $podcast = $this->resolvePodcast($asset, $autoPodcastId, 'auto');
        if (null === $podcast) {
            return;
        }

        if (null === $this->episodeRepo->findOneByAssetAndPodcast($asset, $podcast)) {
            $this->episodeManager->create(
                (new PodcastEpisode())->setPodcast($podcast)->setAsset($asset),
                true,
            );
        }
    }

    /**
     * Resolves the tenant's recommended-podcast target from the asset's ExtSystem and delegates.
     */
    public function syncRecommendedPodcastForAsset(Asset $asset, bool $include): void
    {
        $this->syncRecommendedPodcast(
            $asset,
            $asset->getExtSystem()->getTtsSettings()->getRecommendedPodcastId(),
            $include,
        );
    }

    public function syncRecommendedPodcast(Asset $asset, ?string $recommendedPodcastId, bool $include): void
    {
        if (null === $recommendedPodcastId) {
            return;
        }

        $podcast = $this->resolvePodcast($asset, $recommendedPodcastId, 'recommended');
        if (null === $podcast) {
            return;
        }

        $existing = $this->episodeRepo->findOneByAssetAndPodcast($asset, $podcast);

        if ($include && null === $existing) {
            $this->episodeManager->create(
                (new PodcastEpisode())->setPodcast($podcast)->setAsset($asset),
                true,
            );

            return;
        }

        if (false === $include && null !== $existing) {
            $this->episodeManager->delete($existing, true);
        }
    }

    private function resolvePodcast(Asset $asset, string $podcastId, string $flow): ?Podcast
    {
        $podcast = $this->podcastRepo->find($podcastId);
        if (null === $podcast) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'podcastMembership.podcastNotFound', [
                'flow' => $flow,
                'podcastId' => $podcastId,
                'assetId' => (string) $asset->getId(),
            ]);

            return null;
        }

        if (false === $podcast->getLicence()->is($asset->getLicence())) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'podcastMembership.licenceMismatch', [
                'flow' => $flow,
                'podcastId' => $podcastId,
                'assetId' => (string) $asset->getId(),
                'podcastLicenceId' => (string) $podcast->getLicence()->getId(),
                'assetLicenceId' => (string) $asset->getLicence()->getId(),
            ]);

            return null;
        }

        return $podcast;
    }
}
