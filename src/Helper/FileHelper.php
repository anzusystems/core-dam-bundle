<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use Symfony\Component\Mime\MimeTypes;

final class FileHelper
{
    public static function guessExtension(string $mimeType): string
    {
        $extensions = MimeTypes::getDefault()->getExtensions($mimeType);
        if (isset($extensions[0])) {
            return $extensions[0];
        }

        throw new InvalidArgumentException(sprintf('Can not guess extension for mime type (%s)', $mimeType));
    }

    public static function guessMimeType(string $extension): ?string
    {
        return MimeTypes::getDefault()->guessMimeType($extension);
    }

    public static function checksumFromPath(string $filePath): string
    {
        if (false === file_exists($filePath)) {
            throw new RuntimeException(sprintf('Failed to open file with path (%s)', $filePath));
        }

        return sha1_file($filePath, false);
    }

    public static function partialChecksumFromPath(string $filePath, int $length): string
    {
        if (false === file_exists($filePath)) {
            throw new RuntimeException(sprintf('Failed to open file with path (%s(', $filePath));
        }

        $file = fopen($filePath, 'rb');
        $size = filesize($filePath);
        $content = fread(
            $file,
            $length < $size ? $length : $size
        );

        return sha1($content, false);
    }
}
