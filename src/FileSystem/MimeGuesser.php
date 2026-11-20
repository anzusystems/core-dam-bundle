<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem;

use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegMimeDetector;
use Symfony\Component\Mime\MimeTypesInterface;

final readonly class MimeGuesser
{
    private const string JPEG_START_OF_IMAGE = "\xFF\xD8";
    private const string JPEG_START_OF_SCAN = "\xFF\xDA";
    private const int JPEG_FIRST_APP_MARKER = 0xE0;
    private const int JPEG_LAST_APP_MARKER = 0xEF;
    private const int JPEG_COMMENT_MARKER = 0xFE;

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
            $mimeType = $this->ffmpegMimeDetector->detectAudioMime($path);
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

    /**
     * Checksum of a JPEG without its APPn (EXIF, XMP, IPTC, ICC) and comment segments: agencies that pass on the same
     * photo with rewritten metadata give the same value. Null when the file is not a JPEG with a readable structure.
     */
    public static function imageDataChecksumFromPath(string $filePath): ?string
    {
        $handle = fopen($filePath, 'rb');
        if (false === $handle) {
            throw new RuntimeException(sprintf('Failed to open file with path (%s)', $filePath));
        }

        try {
            if (self::JPEG_START_OF_IMAGE !== fread($handle, 2)) {
                return null;
            }
            $context = hash_init('sha1');
            hash_update($context, self::JPEG_START_OF_IMAGE);
            while (true) {
                $marker = (string) fread($handle, 2);
                if (self::JPEG_START_OF_SCAN === $marker) {
                    hash_update($context, $marker);
                    hash_update_stream($context, $handle);

                    return hash_final($context);
                }
                $length = (string) fread($handle, 2);
                if (2 !== strlen($marker) || "\xFF" !== $marker[0] || 2 !== strlen($length)) {
                    return null;
                }
                $payloadLength = (ord($length[0]) << 8 | ord($length[1])) - 2;
                $payload = $payloadLength > 0 ? (string) fread($handle, $payloadLength) : '';
                if ($payloadLength < 0 || strlen($payload) !== $payloadLength) {
                    return null;
                }
                if (false === self::isJpegMetadataMarker(ord($marker[1]))) {
                    hash_update($context, $marker . $length . $payload);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private static function isJpegMetadataMarker(int $marker): bool
    {
        return self::JPEG_COMMENT_MARKER === $marker
            || ($marker >= self::JPEG_FIRST_APP_MARKER && $marker <= self::JPEG_LAST_APP_MARKER);
    }
}
