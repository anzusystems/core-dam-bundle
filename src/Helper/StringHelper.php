<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\StringNormalizerConfiguration;
use Symfony\Component\String\ByteString;
use Symfony\Component\String\Exception\InvalidArgumentException as UnicodeInvalidArgumentException;

final class StringHelper
{
    public static function parseLength(string $input, int $length): string
    {
        return mb_substr($input, 0, $length);
    }

    public static function getFirstChar(string $string): string
    {
        if (false === empty($string)) {
            return mb_substr($string, 0, 1);
        }

        return '';
    }

    public static function normalize(string $input, StringNormalizerConfiguration $configuration): string
    {
        if ($configuration->isTrim()) {
            $input = trim($input);
        }

        if (false === (null === $configuration->getLength())) {
            $input = mb_substr($input, 0, $configuration->getLength());
        }

        return $input;
    }

    public static function parseString(
        string $input,
        ?int $length = null,
        bool $trim = true,
    ): string {
        $string = htmlspecialchars(strip_tags($input));
        if ($length) {
            $string = self::parseLength($string, $length);
        }

        try {
            $byteString = new ByteString($string);
            if ($trim) {
                $byteString = $byteString->trim();
            }

            return $byteString->toUnicodeString()->toString();
        } catch (UnicodeInvalidArgumentException $exception) {
            return '';
        }
    }
}
