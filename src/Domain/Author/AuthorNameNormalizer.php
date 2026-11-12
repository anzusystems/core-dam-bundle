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
    private const string SYMBOL = '([^\p{L}\p{N}_' . self::WS . '])';
    private const string INVISIBLE = '\x{AD}\x{200B}-\x{200F}\x{FEFF}\x{2060}';
    private const string DASHES = '\x{2010}-\x{2015}\x{2212}';
    private const string DOUBLE_QUOTES = '\x{201C}\x{201D}\x{201E}\x{201F}\x{AB}\x{BB}\x{2033}';
    private const string SINGLE_QUOTES = '\x{2018}\x{2019}\x{201A}\x{201B}\x{B4}`\x{2032}';
    private const string EDGE = self::WS . '.,;:\-\/|';

    private const array LETTERS = [
        'ß' => 'ss',
        'ł' => 'l',
        'ø' => 'o',
        'đ' => 'd',
        'ı' => 'i',
        'æ' => 'ae',
        'œ' => 'oe',
        'ð' => 'd',
        'þ' => 'th',
    ];

    private const array REPLACEMENTS = [
        '/(?![´`])[\p{So}\p{Sk}]/u' => App::EMPTY_STRING,
        '/[' . self::INVISIBLE . ']/u' => App::EMPTY_STRING,
        '/[' . self::WS . ']+/u' => ' ',
        '/[' . self::DASHES . ']/u' => '-',
        '/[' . self::DOUBLE_QUOTES . ']/u' => '"',
        '/[' . self::SINGLE_QUOTES . ']/u' => "'",
        '/[' . self::WS . ']*' . self::SYMBOL . '[' . self::WS . ']*/u' => '$1',
        '/' . self::SYMBOL . '\1+/u' => '$1',
        '/^[' . self::EDGE . ']+|[' . self::EDGE . ']+\z/u' => App::EMPTY_STRING,
    ];

    public static function normalize(string $name): string
    {
        $folded = self::fold($name);
        if (null === $folded) {
            return App::EMPTY_STRING;
        }

        $value = preg_replace(array_keys(self::REPLACEMENTS), array_values(self::REPLACEMENTS), $folded) ?? App::EMPTY_STRING;

        return mb_substr($value, App::ZERO, Author::NAME_MAX_LENGTH);
    }

    private static function fold(string $name): ?string
    {
        $decomposed = Normalizer::normalize($name, Normalizer::FORM_KD);
        if (false === is_string($decomposed)) {
            return null;
        }

        $base = App::EMPTY_STRING;
        foreach (mb_str_split($decomposed) as $char) {
            if (App::ZERO === IntlChar::getCombiningClass(mb_ord($char))) {
                $base .= $char;
            }
        }

        return strtr(mb_strtolower($base), self::LETTERS);
    }
}
