<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Tts\Provider;

use AnzuSystems\CoreDamBundle\Domain\Tts\Provider\TextChunker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextChunkerTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideChunking')]
    public function testChunk(string $text, int $maxCharsPerChunk, array $expected): void
    {
        self::assertSame($expected, (new TextChunker())->chunk($text, $maxCharsPerChunk));
    }

    /**
     * @return iterable<string, array{string, int, list<string>}>
     */
    public static function provideChunking(): iterable
    {
        yield 'empty input → no chunks' => ['', 100, []];
        yield 'under the limit → single chunk' => ['Short narration.', 100, ['Short narration.']];
        yield 'two sentences fitting the limit → packed into one' => ['Aa bb. Cc dd.', 100, ['Aa bb. Cc dd.']];
        yield 'two sentences over the limit → split on the boundary' => ['Aaaaa. Bbbbb.', 8, ['Aaaaa.', 'Bbbbb.']];
        yield 'greedy packing then overflow → two chunks' => ['Ab. Cd. Efghij.', 8, ['Ab. Cd.', 'Efghij.']];
        yield 'single sentence over the limit → emitted whole, never broken mid-sentence' => ['Aaaaaaaaaa', 5, ['Aaaaaaaaaa']];
        yield 'multibyte counted by characters, not bytes' => ['Ááááá. Ééééé.', 8, ['Ááááá.', 'Ééééé.']];
    }
}
