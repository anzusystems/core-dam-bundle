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
