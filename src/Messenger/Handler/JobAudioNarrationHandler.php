<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\JobAudioNarrationManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsJobOrchestrator;
use AnzuSystems\CoreDamBundle\Entity\JobAudioNarration;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\JobAudioNarrationMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsJobMode;
use AnzuSystems\CoreDamBundle\Repository\JobAudioNarrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * `dam-tts` queue worker. Synth (slow HTTP) and ffmpeg run OUTSIDE any DB transaction — only short
 * persist+commit windows hold locks. Job pipeline lives in {@see TtsJobOrchestrator}.
 */
#[AsMessageHandler]
final readonly class JobAudioNarrationHandler
{
    public function __construct(
        private JobAudioNarrationRepository $jobRepo,
        private JobAudioNarrationManager $jobManager,
        private TtsJobOrchestrator $orchestrator,
        private EntityManagerInterface $entityManager,
        private DamLogger $logger,
    ) {
    }

    public function __invoke(JobAudioNarrationMessage $message): void
    {
        $job = $this->jobRepo->find($message->jobId);
        if (false === $job instanceof JobAudioNarration) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'handler.jobNotFound', ['jobId' => $message->jobId]);

            return;
        }

        $this->jobManager->markProcessing($job);

        try {
            match ($job->getMode()) {
                TtsJobMode::Initial => $this->orchestrator->processInitial($job),
                TtsJobMode::Regenerate => $this->orchestrator->processRegenerate($job),
            };
        } catch (Throwable $e) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'handler.jobFailed', [
                'jobId' => (string) $job->getId(),
                'mode' => $job->getMode()->value,
            ], exception: $e);

            // Never rethrow — Messenger retry would re-process a terminal job (openInitialKey is
            // already cleared). Callers must dispatch a fresh job for a retry.
            $this->handleJobFailure($job, $e);
        }
    }

    private function handleJobFailure(JobAudioNarration $job, Throwable $e): void
    {
        try {
            $this->entityManager->wrapInTransaction(
                function () use ($job, $e): void {
                    $this->jobManager->markFailed($job, $e->getMessage(), false);
                    $this->entityManager->flush();
                }
            );
        } catch (Throwable $failureEx) {
            $this->logger->error(DamLogger::NAMESPACE_TTS, 'handler.markFailedFailed', [
                'jobId' => (string) $job->getId(),
            ], exception: $failureEx);
        }
    }
}
