<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Tts;

use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testEnabledExposesTargetLufs(): void
    {
        self::assertSame(-18.0, self::config(enabled: true, targetLufs: -18.0)->getTargetLufs());
    }

    /**
     * Disabled means the target is unused — a garbage value must not break the app.
     */
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
