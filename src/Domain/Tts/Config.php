<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use DateTimeImmutable;

/** App-level TTS config; slot names are per-app because each ext-system's file_slots whitelist must match. */
final readonly class Config
{
    public const string PREVIEW_STORAGE_PREFIX = 'tts/preview/';

    public const float NORMALIZATION_TRUE_PEAK_DBTP = -1.5;
    public const float NORMALIZATION_LRA = 11.0;

    // Unreplaced `#{TTS_TARGET_LUFS}#` casts to 0.0 → master at a deafening 0 LUFS; fail loudly instead.
    public const float TARGET_LUFS_MIN = -30.0;
    public const float TARGET_LUFS_MAX = -10.0;

    public function __construct(
        private string $systemDefaultFamilySlug,
        private int $chunkSizeChars,
        private string $masterSlotName,
        private string $previewSlotName,
        private int $audioRetentionGraceSeconds,
        private bool $loudnessNormalizationEnabled,
        private float $targetLufs,
        private int $previewMinDurationSeconds = 30,
        private int $previewMaxDurationSeconds = 90,
        private float $previewDurationRatio = 0.4,
    ) {
        if ($loudnessNormalizationEnabled && ($targetLufs < self::TARGET_LUFS_MIN || $targetLufs > self::TARGET_LUFS_MAX)) {
            throw new RuntimeException(sprintf(
                'TTS_TARGET_LUFS must be between %s and %s when loudness normalization is enabled, got %s.',
                self::TARGET_LUFS_MIN,
                self::TARGET_LUFS_MAX,
                $targetLufs,
            ));
        }
    }

    /**
     * A short narration unlocks at most the configured ratio of its length; a long one caps at the max preview length.
     */
    public function getPreviewDurationSeconds(int $masterDurationSeconds): int
    {
        return min(
            $this->previewMaxDurationSeconds,
            max(
                $this->previewMinDurationSeconds,
                (int) floor($masterDurationSeconds * $this->previewDurationRatio),
            ),
        );
    }

    public function getTargetLufs(): ?float
    {
        return $this->loudnessNormalizationEnabled ? $this->targetLufs : null;
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

    public function getAudioRetentionExpireAt(): DateTimeImmutable
    {
        return App::getAppDate()->modify(sprintf('+%d seconds', $this->audioRetentionGraceSeconds));
    }
}
