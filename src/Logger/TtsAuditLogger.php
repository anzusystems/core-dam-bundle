<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Logger;

/**
 * Emits TTS audit events into the journal log stream — swap, cancel, unpublish, initialCancelled.
 */
final readonly class TtsAuditLogger
{
    public function __construct(
        private DamLogger $logger,
    ) {
    }

    /**
     * @param list<string> $oldAudioFileIds
     * @param list<string> $newAudioFileIds
     */
    public function logSwapped(
        string $assetId,
        string $requestId,
        array $oldAudioFileIds,
        array $newAudioFileIds,
        ?string $voiceFamilySlug = null,
        ?string $sourceTextHash = null,
    ): void {
        $this->logger->info(DamLogger::NAMESPACE_TTS, 'audit.swapped', [
            'assetId' => $assetId,
            'requestId' => $requestId,
            'oldAudioFileIds' => $oldAudioFileIds,
            'newAudioFileIds' => $newAudioFileIds,
            'voiceFamilySlug' => $voiceFamilySlug,
            'sourceTextHash' => $sourceTextHash,
        ]);
    }

    public function logCancelled(string $assetId, ?string $requestId, ?string $userId, ?string $reason): void
    {
        $this->logger->info(DamLogger::NAMESPACE_TTS, 'audit.cancelled', [
            'assetId' => $assetId,
            'requestId' => $requestId,
            'userId' => $userId,
            'reason' => $reason,
        ]);
    }

    public function logUnpublished(string $assetId, ?string $userId, ?string $reason): void
    {
        $this->logger->info(DamLogger::NAMESPACE_TTS, 'audit.unpublished', [
            'assetId' => $assetId,
            'userId' => $userId,
            'reason' => $reason,
        ]);
    }

    public function logInitialCancelled(string $requestId, ?string $userId, ?string $reason): void
    {
        $this->logger->info(DamLogger::NAMESPACE_TTS, 'audit.initialCancelled', [
            'requestId' => $requestId,
            'userId' => $userId,
            'reason' => $reason,
        ]);
    }
}
