<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts;

/**
 * App-level TTS configuration. Slot names are per-app configurable because each ext-system declares
 * its own `file_slots.slots` whitelist that the master/preview pair must match.
 */
final readonly class Config
{
    public const string PREVIEW_STORAGE_PREFIX = 'tts/preview/';

    public function __construct(
        private string $systemDefaultFamilySlug,
        private int $chunkSizeChars,
        private string $masterSlotName,
        private string $previewSlotName,
        private int $audioRetentionGraceSeconds,
    ) {
    }

    public function getSystemDefaultFamilySlug(): string
    {
        return $this->systemDefaultFamilySlug;
    }

    public function getChunkSizeChars(): int
    {
        return $this->chunkSizeChars;
    }

    public function getMasterSlotName(): string
    {
        return $this->masterSlotName;
    }

    public function getPreviewSlotName(): string
    {
        return $this->previewSlotName;
    }

    /**
     * Grace period (seconds) for which a superseded TTS audio file — and thus its public CDN URL —
     * is kept alive after a regeneration before the {@see \AnzuSystems\CoreDamBundle\Command\TtsClearExpiredAudioCommand}
     * cron deletes it. Lets still-cached consumer responses pointing at the old public-bucket path keep streaming.
     */
    public function getAudioRetentionGraceSeconds(): int
    {
        return $this->audioRetentionGraceSeconds;
    }
}
