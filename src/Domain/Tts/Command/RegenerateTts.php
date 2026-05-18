<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Command;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\JobAudioNarration;
use AnzuSystems\CoreDamBundle\Messenger\Message\JobAudioNarrationMessage;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\JobAudioNarrationManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetLocker;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsLifecycle;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsJobMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RegenerateTts
{
    public function __construct(
        private TtsAssetLocker $assetLocker,
        private JobAudioNarrationManager $jobManager,
        private TtsAssetManager $ttsAssetManager,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function execute(
        string $stableAssetId,
        ?string $voiceFamilySlug,
    ): JobAudioNarration {
        App::throwOnReadOnlyMode();

        $job = $this->entityManager->wrapInTransaction(
            function () use ($stableAssetId, $voiceFamilySlug): JobAudioNarration {
                $ttsAsset = $this->assetLocker->lockExpecting($stableAssetId, TtsLifecycle::ACTIVE_ONLY);

                $job = (new JobAudioNarration())
                    ->setMode(TtsJobMode::Regenerate)
                    ->setStableAssetId($stableAssetId)
                    ->setVoiceFamilySlug($voiceFamilySlug);
                $this->jobManager->create($job, false);

                $this->ttsAssetManager->markSuperseding($ttsAsset, (string) $job->getId());

                $this->entityManager->flush();

                return $job;
            }
        );

        $this->messageBus->dispatch(new JobAudioNarrationMessage((string) $job->getId(), TtsJobMode::Regenerate->value));

        return $job;
    }
}
