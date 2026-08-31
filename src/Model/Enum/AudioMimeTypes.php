<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;

enum AudioMimeTypes: string implements EnumInterface
{
    use BaseEnumTrait;

    public const string PUBLIC_CONVERSION_EXTENSION = FfmpegService::AUDIO_EXTENSION_MP3;
    public const int PUBLIC_CONVERSION_BITRATE_KBPS = 128;

    public const array CHOICES = [
        self::MIME_AUDIO_MP4,
        self::MIME_WAV,
        self::MIME_X_WAV,
        self::MIME_MPEG,
        self::MIME_M4A,
        self::MIME_X_M4A,
    ];

    private const string MIME_AUDIO_MP4 = 'audio/mp4';
    private const string MIME_WAV = 'audio/wav';
    private const string MIME_X_WAV = 'audio/x-wav';
    private const string MIME_MPEG = 'audio/mpeg';
    private const string MIME_M4A = 'audio/m4a';
    private const string MIME_X_M4A = 'audio/x-m4a';

    case MimeMp4 = self::MIME_AUDIO_MP4;
    case MimeWaw = self::MIME_WAV;
    case MimeXWaw = self::MIME_X_WAV;
    case MimeMpeg = self::MIME_MPEG;
    case MimeM4a = self::MIME_M4A;
    case MimeXm4a = self::MIME_X_M4A;

    public static function getBrowserTypes(): array
    {
        return [
            self::MimeMpeg
        ];
    }

    /**
     * Uncompressed PCM must not reach the public CDN (~18x mp3 bandwidth, above CloudFlare cacheable file size).
     */
    public static function requiresPublicMp3Conversion(string $mimeType): bool
    {
        return self::tryFrom($mimeType)?->in([self::MimeWaw, self::MimeXWaw]) ?? false;
    }
}
