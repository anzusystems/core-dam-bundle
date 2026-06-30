<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Entity;

use AnzuSystems\CoreDamBundle\Entity\ElevenlabsVoice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ElevenlabsVoiceTest extends TestCase
{
    #[DataProvider('provideModels')]
    public function testSupportsRequestStitching(string $modelId, bool $expected): void
    {
        $voice = (new ElevenlabsVoice())->setModelId($modelId);

        self::assertSame($expected, $voice->supportsRequestStitching());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideModels(): iterable
    {
        yield 'default multilingual_v2 supports stitching' => [ElevenlabsVoice::MODEL_DEFAULT, true];
        yield 'turbo_v2_5 supports stitching' => ['eleven_turbo_v2_5', true];
        yield 'v3 does not support stitching' => ['eleven_v3', false];
        yield 'future v4 not in allowlist → no stitching' => ['eleven_v4', false];
        yield 'unknown model → no stitching' => ['some_unknown_model', false];
    }
}
