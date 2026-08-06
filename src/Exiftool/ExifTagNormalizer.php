<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exiftool;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;

/**
 * Turns one decoded exiftool JSON object into the flat tag => value map the rest of the system
 * works with. Kept separate from {@see Exiftool} so callers that run exiftool themselves — a batch
 * read over many files, for instance — normalize tags exactly like the single-file ingest path
 * instead of growing their own copy of the charset rules.
 */
final readonly class ExifTagNormalizer
{
    // Exiftool's "latin1" IPTC charset is actually its "Latin" table (cp1252, not literal ISO-8859-1,
    // see exiftool's Charset/Latin.pm: "cp1252 to Unicode ... table omits 1-byte characters with the
    // same values as Unicode"): most bytes 0x80-0x9F pass through as U+0080-U+009F, but these 27 map to
    // smart-quote/Š/Ž-style punctuation above U+00FF instead. Reversing that restores true 1:1 byte
    // passthrough, which normalizeIptcCharset() below depends on.
    private const array LATIN_CHARSET_OVERRIDE_UNDO = [
        "\u{20AC}" => "\u{0080}", "\u{201A}" => "\u{0082}", "\u{0192}" => "\u{0083}",
        "\u{201E}" => "\u{0084}", "\u{2026}" => "\u{0085}", "\u{2020}" => "\u{0086}",
        "\u{2021}" => "\u{0087}", "\u{02C6}" => "\u{0088}", "\u{2030}" => "\u{0089}",
        "\u{0160}" => "\u{008A}", "\u{2039}" => "\u{008B}", "\u{0152}" => "\u{008C}",
        "\u{017D}" => "\u{008E}", "\u{2018}" => "\u{0091}", "\u{2019}" => "\u{0092}",
        "\u{201C}" => "\u{0093}", "\u{201D}" => "\u{0094}", "\u{2022}" => "\u{0095}",
        "\u{2013}" => "\u{0096}", "\u{2014}" => "\u{0097}", "\u{02DC}" => "\u{0098}",
        "\u{2122}" => "\u{0099}", "\u{0161}" => "\u{009A}", "\u{203A}" => "\u{009B}",
        "\u{0153}" => "\u{009C}", "\u{017E}" => "\u{009E}", "\u{0178}" => "\u{009F}",
    ];

    public function __construct(
        private ?string $iptcFallbackCharset = null,
    ) {
    }

    /**
     * @param array<string, mixed> $decodedTags
     *
     * @return array<string, string>
     */
    public function normalizeTags(array $decodedTags): array
    {
        $tagList = [];
        foreach ($decodedTags as $tagName => $tagValue) {
            if (is_scalar($tagValue)) {
                $tagList[$tagName] = StringHelper::repairDoubleEncodedUtf8(
                    trim($this->normalizeIptcCharset((string) $tagValue))
                );

                continue;
            }
            // List tags (Keywords, Subject) come back as JSON arrays; keep the flat form consumers already expect.
            if (is_array($tagValue)) {
                $values = array_map(
                    fn (mixed $item): string => $this->normalizeIptcCharset((string) $item),
                    array_filter($tagValue, 'is_scalar'),
                );
                $tagList[$tagName] = StringHelper::repairDoubleEncodedUtf8(implode(', ', $values));
            }
        }

        return $tagList;
    }

    // Pure ASCII and text already above U+00FF never went through the latin1 byte-passthrough read
    // (Exiftool::buildReadTags()) — it's declared UTF-8/XMP/EXIF — so both are returned untouched. A value
    // confined to U+0080-U+00FF is passthrough bytes reinterpreted as Latin-1, recoverable losslessly; if
    // those bytes are themselves valid UTF-8, the source file was undeclared UTF-8, otherwise fall back to
    // the configured legacy charset — non-trivial cp1250 text virtually never forms valid UTF-8 by chance,
    // so the validity check is a deterministic discriminator.
    private function normalizeIptcCharset(string $value): string
    {
        if (null === $this->iptcFallbackCharset || App::EMPTY_STRING === $this->iptcFallbackCharset) {
            return $value;
        }

        if (mb_check_encoding($value, 'ASCII')) {
            return $value;
        }

        $passthrough = strtr($value, self::LATIN_CHARSET_OVERRIDE_UNDO);
        if (1 === preg_match('/[^\x{00}-\x{FF}]/u', $passthrough)) {
            return $value;
        }

        $bytes = mb_convert_encoding($passthrough, 'ISO-8859-1', 'UTF-8');
        if (mb_check_encoding($bytes, 'UTF-8')) {
            return $bytes;
        }

        $recovered = iconv($this->iptcFallbackCharset, 'UTF-8', $bytes);

        return false === $recovered ? $value : $recovered;
    }
}
