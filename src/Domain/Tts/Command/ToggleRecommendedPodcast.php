<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Command;

use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\PodcastMembership;

use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;

use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ToggleRecommendedPodcast
{
    public function __construct(
        private TtsAssetLocker $ttsAssetLocker,
        private TtsAssetManager $ttsAssetManager,
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
            $ttsAsset = $this->ttsAssetLocker->requireFor($asset);

            $this->ttsAssetManager->setIncludeInRecommendedPodcast($ttsAsset, $include);
            $this->podcastMembership->syncRecommendedPodcast($asset, $ttsAsset->getRecommendedPodcastId(), $include);

            $this->entityManager->flush();
        });

        return $include;
    }
}
