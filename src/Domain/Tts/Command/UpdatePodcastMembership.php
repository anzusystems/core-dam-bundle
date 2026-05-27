<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Replace the full set of podcast memberships for a TTS asset (PUT semantics).
 * Licence-mismatched podcasts are logged + skipped — sync never aborts the request.
 */
final readonly class UpdatePodcastMembership
{
    public function __construct(
        private TtsAssetLocker $ttsAssetLocker,
        private PodcastEpisodeManager $episodeManager,
        private DamLogger $logger,
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

        $desired = $this->filterByLicence($asset, $podcasts);

        $this->entityManager->wrapInTransaction(function () use ($asset, $desired): void {
            $this->ttsAssetLocker->requireFor($asset);
            $this->episodeManager->setMembership($asset, $desired);
        });
    }

    /**
     * @param Collection<int, Podcast> $podcasts
     *
     * @return Collection<int, Podcast>
     */
    private function filterByLicence(Asset $asset, Collection $podcasts): Collection
    {
        $desired = new ArrayCollection();
        foreach ($podcasts as $podcast) {
            if ($podcast->getLicence()->is($asset->getLicence())) {
                $desired->add($podcast);

                continue;
            }

            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'podcastMembership.licenceMismatch', [
                'podcastId' => (string) $podcast->getId(),
                'assetId' => (string) $asset->getId(),
                'podcastLicenceId' => (string) $podcast->getLicence()->getId(),
                'assetLicenceId' => (string) $asset->getLicence()->getId(),
            ]);
        }

        return $desired;
    }
}
