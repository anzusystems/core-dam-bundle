<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Author;

use AnzuSystems\CoreDamBundle\Domain\Author\AuthorNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthorNameNormalizerTest extends TestCase
{
    #[DataProvider('equalNamesDataProvider')]
    public function testEqualNamesShareKey(string $first, string $second): void
    {
        self::assertSame(AuthorNameNormalizer::normalize($second), AuthorNameNormalizer::normalize($first));
    }

    public static function equalNamesDataProvider(): array
    {
        return [
            'diacritics' => ['Jakubco', 'Jakubčo'],
            'special letters' => ['Łukasz Gągulski', 'Lukasz Gagulski'],
            'soft hyphen' => ["\u{AD}SITA", 'SITA'],
            'whitespace run' => ['TASR  RADOVAN STOKLASA', 'TASR RADOVAN STOKLASA'],
            'dash and case' => ['SME – JOZEF JAKUBČO', 'SME - Jozef Jakubčo'],
            'curly apostrophe' => ['McDonald’s Cup', "McDonald's Cup"],
            'symbol' => ['© EPA', 'EPA'],
            'spaces around slash' => ['TASR / MILAN KAPUSTA', 'TASR/Milan Kapusta'],
            'repeated slash' => ['TASR//AP', 'TASR/AP'],
            'trailing dot' => ['Jozef Jakubčo.', 'Jozef Jakubčo'],
            'sharp s' => ['Strauß', 'Strauss'],
        ];
    }

    #[DataProvider('keyDataProvider')]
    public function testKey(string $name, string $expected): void
    {
        self::assertSame($expected, AuthorNameNormalizer::normalize($name));
    }

    public static function keyDataProvider(): array
    {
        return [
            'only punctuation' => ['......', ''],
            'empty' => ['', ''],
            'cyrillic is only lowercased' => ['ЖУРНАЛ Иван', 'журнал иван'],
            'accent mark folds to space' => ['O´Brien', 'o brien'],
            'quotes unified' => ['«Pravda»', '"pravda"'],
            'ligature' => ['Æther Þor', 'aether thor'],
        ];
    }
}
