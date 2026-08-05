<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Tts;

use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    #[DataProvider('provideAcceptedTargets')]
    public function testEnabledExposesTargetLufs(float $targetLufs): void
    {
        self::assertSame($targetLufs, self::config(enabled: true, targetLufs: $targetLufs)->getTargetLufs());
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function provideAcceptedTargets(): iterable
    {
        yield 'typical podcast level' => [-18.0];
        yield 'quiet boundary is inclusive' => [Config::TARGET_LUFS_MIN];
        yield 'loud boundary is inclusive' => [Config::TARGET_LUFS_MAX];
    }

    public function testDisabledExposesNullAndIgnoresTarget(): void
    {
        self::assertNull(self::config(enabled: false, targetLufs: 0.0)->getTargetLufs());
    }

    #[DataProvider('provideOutOfBandTargets')]
    public function testEnabledRejectsOutOfBandTarget(float $targetLufs): void
    {
        $this->expectException(RuntimeException::class);

        self::config(enabled: true, targetLufs: $targetLufs);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function provideOutOfBandTargets(): iterable
    {
        yield 'unreplaced deploy token cast to 0.0 → deafening master' => [0.0];
        yield 'far too loud' => [-2.0];
        yield 'far too quiet' => [-40.0];
        yield 'just past the loud boundary' => [Config::TARGET_LUFS_MAX + 0.01];
        yield 'just past the quiet boundary' => [Config::TARGET_LUFS_MIN - 0.01];
    }

    #[DataProvider('providePreviewDurations')]
    public function testPreviewDurationClampsRatioBetweenMinAndMax(int $masterDurationSeconds, int $expected): void
    {
        self::assertSame(
            $expected,
            self::config(enabled: false, targetLufs: 0.0)->getPreviewDurationSeconds($masterDurationSeconds),
        );
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function providePreviewDurations(): iterable
    {
        yield 'unprocessed master (0s) falls back to the minimum' => [0, 30];
        yield 'short narration stays at the minimum' => [60, 30];
        yield 'mid-length narration unlocks the ratio share' => [150, 60];
        yield 'fraction floors so at most the ratio share unlocks' => [149, 59];
        yield 'boundary narration reaches the maximum exactly' => [225, 90];
        yield 'long narration caps at the maximum' => [600, 90];
    }

    private static function config(bool $enabled, float $targetLufs): Config
    {
        return new Config(
            systemDefaultFamilySlug: 'sme_default_male',
            chunkSizeChars: 4_800,
            masterSlotName: 'paid',
            previewSlotName: 'free',
            audioRetentionGraceSeconds: 86_400,
            loudnessNormalizationEnabled: $enabled,
            targetLufs: $targetLufs,
        );
    }
}
