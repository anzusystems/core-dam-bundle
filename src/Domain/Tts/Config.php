<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts;

use AnzuSystems\CoreDamBundle\Model\Enum\TtsProvider;

/**
 * App-level TTS configuration. Slot names are per-app configurable because each ext-system declares
 * its own `file_slots.slots` whitelist that the master/preview pair must match.
 */
final readonly class Config
{
    public const string PREVIEW_STORAGE_PREFIX = 'tts/preview/';

    public function __construct(
        private string $activeProvider,
        private string $systemDefaultFamilySlug,
        private int $chunkSizeChars,
        private string $masterSlotName,
        private string $previewSlotName,
    ) {
    }

    public function getActiveProvider(): TtsProvider
    {
        return TtsProvider::from($this->activeProvider);
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
}
