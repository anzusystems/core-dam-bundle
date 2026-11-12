<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Author;
use IntlChar;
use Normalizer;

final class AuthorNameNormalizer
{
    // Python str.isspace() set, not PCRE \s: the key must stay identical to the analysed reference.
    private const string WS = '\x{9}-\x{D}\x{1C}-\x{20}\x{85}\x{A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}';
    private const string WORD = '\p{L}\p{N}_';
    private const array SPECIAL_LETTERS = [
        'ł' => 'l',
        'ø' => 'o',
        'đ' => 'd',
        'ı' => 'i',
        'æ' => 'ae',
        'œ' => 'oe',
        'ð' => 'd',
        'þ' => 'th',
    ];

    public static function normalize(string $name): string
    {
        $folded = self::fold($name);
        if (null === $folded) {
            return '';
        }

        $value = preg_replace('/(?![´`])[\p{So}\p{Sk}]/u', '', $folded) ?? '';
        $value = preg_replace('/[\x{AD}\x{200B}-\x{200F}\x{FEFF}\x{2060}]/u', '', $value) ?? '';
        $value = preg_replace('/[' . self::WS . ']+/u', ' ', $value) ?? '';
        $value = preg_replace('/^[' . self::WS . ']+|[' . self::WS . ']+\z/u', '', $value) ?? '';
        $value = strtr($value, self::SPECIAL_LETTERS);
        $value = preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $value) ?? '';
        $value = preg_replace('/[\x{201C}\x{201D}\x{201E}\x{201F}\x{AB}\x{BB}\x{2033}]/u', '"', $value) ?? '';
        $value = preg_replace('/[\x{2018}\x{2019}\x{201A}\x{201B}\x{B4}`\x{2032}]/u', "'", $value) ?? '';
        $symbol = '([^' . self::WORD . self::WS . '])';
        $value = preg_replace('/[' . self::WS . ']*' . $symbol . '[' . self::WS . ']*/u', '$1', $value) ?? '';
        $value = preg_replace('/' . $symbol . '\1+/u', '$1', $value) ?? '';
        $value = preg_replace('/^[' . self::WS . '.,;:\-\/|]+|[' . self::WS . '.,;:\-\/|]+\z/u', '', $value) ?? '';

        return mb_substr($value, App::ZERO, Author::NAME_MAX_LENGTH);
    }

    private static function fold(string $name): ?string
    {
        $decomposed = Normalizer::normalize($name, Normalizer::FORM_KD);
        if (false === is_string($decomposed)) {
            return null;
        }

        $base = '';
        foreach (mb_str_split($decomposed) as $char) {
            if (App::ZERO === IntlChar::getCombiningClass(mb_ord($char))) {
                $base .= $char;
            }
        }

        return str_replace('ß', 'ss', mb_strtolower($base));
    }
}
