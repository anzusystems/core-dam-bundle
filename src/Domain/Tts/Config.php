<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts;

/** App-level TTS config; slot names are per-app because each ext-system's file_slots whitelist must match. */
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
     * Grace window keeping a superseded audio URL alive so cached CDN responses keep streaming.
     */
    public function getAudioRetentionGraceSeconds(): int
    {
        return $this->audioRetentionGraceSeconds;
    }
}
