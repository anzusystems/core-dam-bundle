<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem;

use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegMimeDetector;
use Symfony\Component\Mime\MimeTypesInterface;

final readonly class MimeGuesser
{
    public function __construct(
        private MimeTypesInterface $mimeTypes,
        private FfmpegMimeDetector $ffmpegMimeDetector,
    ) {
    }

    public function guessExtension(string $mimeType): string
    {
        $extensions = $this->mimeTypes->getExtensions($mimeType);

        if (isset($extensions[0])) {
            return $extensions[0];
        }

        throw new InvalidArgumentException(sprintf('Can not guess extension for mime type (%s)', $mimeType));
    }

    public function guessMime(string $path, bool $useFfmpeg = false): string
    {
        $mimeType = null;
        if ($useFfmpeg) {
            $mimeType = $this->ffmpegMimeDetector->detectMime($path);
        }

        if ($mimeType) {
            return $mimeType;
        }

        $mimeType = $this->mimeTypes->guessMimeType($path);

        if (null === $mimeType) {
            throw new InvalidArgumentException(sprintf('Can not guess mime type for file (%s)', $path));
        }

        return $mimeType;
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
