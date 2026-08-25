<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exiftool;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;

/**
 * Turns one decoded exiftool JSON object into the flat tag => value map, so every caller
 * (single-file ingest and batch readers alike) shares the same normalization and charset rules.
 */
final readonly class ExifTagNormalizer
{
    // Undo of exiftool's "latin1" (really cp1252, see exiftool Charset/Latin.pm) mappings above U+00FF,
    // restoring true 1:1 byte passthrough for normalizeIptcCharset(); exercised in ExifTagNormalizerTest.
    private const array LATIN_CHARSET_OVERRIDE_UNDO = [
        "\u{20AC}" => "\u{0080}",
        "\u{201A}" => "\u{0082}",
        "\u{0192}" => "\u{0083}",
        "\u{201E}" => "\u{0084}",
        "\u{2026}" => "\u{0085}",
        "\u{2020}" => "\u{0086}",
        "\u{2021}" => "\u{0087}",
        "\u{02C6}" => "\u{0088}",
        "\u{2030}" => "\u{0089}",
        "\u{0160}" => "\u{008A}",
        "\u{2039}" => "\u{008B}",
        "\u{0152}" => "\u{008C}",
        "\u{017D}" => "\u{008E}",
        "\u{2018}" => "\u{0091}",
        "\u{2019}" => "\u{0092}",
        "\u{201C}" => "\u{0093}",
        "\u{201D}" => "\u{0094}",
        "\u{2022}" => "\u{0095}",
        "\u{2013}" => "\u{0096}",
        "\u{2014}" => "\u{0097}",
        "\u{02DC}" => "\u{0098}",
        "\u{2122}" => "\u{0099}",
        "\u{0161}" => "\u{009A}",
        "\u{203A}" => "\u{009B}",
        "\u{0153}" => "\u{009C}",
        "\u{017E}" => "\u{009E}",
        "\u{0178}" => "\u{009F}",
    ];

    public function __construct(
        private ?string $iptcFallbackCharset = null,
    ) {
    }

    /**
     * @param array<string, mixed> $decodedTags
     * @param string[]|null $iptcTagNames tags read from the IPTC group — charset recovery applies only to them; null applies it to every tag (for callers without group info)
     *
     * @return array<string, string>
     */
    public function normalizeTags(array $decodedTags, ?array $iptcTagNames = null): array
    {
        $tagList = [];
        foreach ($decodedTags as $tagName => $tagValue) {
            $recoverCharset = null === $iptcTagNames || in_array($tagName, $iptcTagNames, true);
            if (is_scalar($tagValue)) {
                $tagList[$tagName] = StringHelper::repairDoubleEncodedUtf8(
                    trim($this->normalizeValue((string) $tagValue, $recoverCharset))
                );

                continue;
            }
            // List tags (Keywords, Subject) come back as JSON arrays; keep the flat form consumers already expect.
            if (is_array($tagValue)) {
                $values = array_map(
                    fn (mixed $item): string => $this->normalizeValue((string) $item, $recoverCharset),
                    array_filter($tagValue, 'is_scalar'),
                );
                $tagList[$tagName] = StringHelper::repairDoubleEncodedUtf8(implode(', ', $values));
            }
        }

        return $tagList;
    }

    private function normalizeValue(string $value, bool $recoverCharset): string
    {
        return $recoverCharset ? $this->normalizeIptcCharset($value) : $value;
    }

    // ASCII and text above U+00FF never went through the byte-passthrough read — both stay untouched.
    // Passthrough bytes valid as UTF-8 mean undeclared UTF-8; anything else recovers via the fallback charset.
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
