<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Exiftool;

use AnzuSystems\CoreDamBundle\Exiftool\ExifTagNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Charset-recovery inputs simulate what exiftool's byte-passthrough read ("latin1", really its cp1252
 * "Latin" table) produces: raw IPTC bytes 0xA0-0xFF arrive as U+00A0-U+00FF one to one, while 27 bytes
 * in 0x80-0x9F arrive as cp1252 punctuation above U+00FF (0x84 => U+201E, 0x9C => U+0153, ...) and must
 * be undone by LATIN_CHARSET_OVERRIDE_UNDO before byte-level recovery.
 */
final class ExifTagNormalizerTest extends TestCase
{
    public function testDisabledFallbackCharsetLeavesValuesUntouched(): void
    {
        $normalizer = new ExifTagNormalizer(null);

        self::assertSame(
            [
                'By-line' => "Ren\u{E8}",
                'Caption-Abstract' => "\u{201E}Ahoj\u{201C}",
            ],
            $normalizer->normalizeTags(
                [
                    'By-line' => "Ren\u{E8}",
                    'Caption-Abstract' => "\u{201E}Ahoj\u{201C}",
                ],
                ['By-line', 'Caption-Abstract'],
            ),
        );
    }

    public function testUndeclaredUtf8BytesAreDecodedAsUtf8(): void
    {
        $normalizer = new ExifTagNormalizer('cp1250');

        // Raw UTF-8 bytes C5 BD C3 A1 6B ("Žák") read via passthrough as one char per byte.
        self::assertSame(
            ['By-line' => 'Žák'],
            $normalizer->normalizeTags(['By-line' => "\u{C5}\u{BD}\u{C3}\u{A1}k"], ['By-line']),
        );
    }

    public function testCp1250BytesInLatinOverrideRangeAreRecovered(): void
    {
        $normalizer = new ExifTagNormalizer('cp1250');

        // cp1250 bytes 84 ("„"), 93 ("“"), 96 ("–"), 9C ("ś") arrive as cp1252 punctuation and must survive the undo table.
        self::assertSame(
            ['Caption-Abstract' => "\u{201E}Ahoj\u{201C} \u{2013} \u{15B}lub"],
            $normalizer->normalizeTags(['Caption-Abstract' => "\u{201E}Ahoj\u{201C} \u{2013} \u{153}lub"], ['Caption-Abstract']),
        );
    }

    public function testTextAboveLatinRangeIsNeverReinterpreted(): void
    {
        $normalizer = new ExifTagNormalizer('cp1250');

        self::assertSame(
            ['Artist' => 'Peter Žákovič'],
            $normalizer->normalizeTags(['Artist' => 'Peter Žákovič'], ['Artist']),
        );
    }

    public function testRecoveryAppliesOnlyToGivenIptcTagNames(): void
    {
        $normalizer = new ExifTagNormalizer('cp1250');

        // Same Latin-1-range value: the EXIF tag keeps "è" while the IPTC tag recovers to cp1250 "č".
        self::assertSame(
            ['Artist' => "Ren\u{E8}", 'By-line' => "Ren\u{10D}"],
            $normalizer->normalizeTags(
                ['Artist' => "Ren\u{E8}", 'By-line' => "Ren\u{E8}"],
                ['By-line'],
            ),
        );
    }

    public function testListTagIsFlattenedAndRecoveredPerItem(): void
    {
        $normalizer = new ExifTagNormalizer('cp1250');

        self::assertSame(
            ['Keywords' => "\u{15B}, beta"],
            $normalizer->normalizeTags(['Keywords' => ["\u{153}", 'beta']], ['Keywords']),
        );
    }
}
