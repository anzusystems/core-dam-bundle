<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AudioMimeTypes: string implements EnumInterface
{
    use BaseEnumTrait;

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
}
