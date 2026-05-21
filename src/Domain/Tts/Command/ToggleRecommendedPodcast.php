<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\PodcastMembership;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Admin toggle for "this TTS asset belongs to the recommended podcast" membership. The boolean
 * state IS the {@see \AnzuSystems\CoreDamBundle\Entity\PodcastEpisode} row existence — no flag
 * on TtsAsset. Target podcast is tenant-scoped ({@see ExtSystemTtsSettings::recommendedPodcastId}).
 *
 * Concurrent toggles for the same asset serialise via the PodcastEpisode UNIQUE (asset, podcast)
 * constraint enforced by {@see PodcastMembership} — no separate row lock needed.
 */
final readonly class ToggleRecommendedPodcast
{
    public function __construct(
        private TtsAssetRepository $ttsAssetRepo,
        private PodcastMembership $podcastMembership,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws RegenCancelledException if the asset is not a TTS asset
     */
    public function execute(Asset $asset, bool $include): bool
    {
        App::throwOnReadOnlyMode();

        $this->entityManager->wrapInTransaction(function () use ($asset, $include): void {
            if (null === $this->ttsAssetRepo->findByAsset($asset)) {
                throw new RegenCancelledException(sprintf('Asset "%s" is not a TTS asset.', (string) $asset->getId()));
            }

            $this->podcastMembership->syncRecommendedPodcastForAsset($asset, $include);

            $this->entityManager->flush();
        });

        return $include;
    }
}
