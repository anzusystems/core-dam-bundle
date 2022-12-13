<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AudioMimeTypes: string implements EnumInterface
{
    use BaseEnumTrait;

    public const CHOICES = [
        self::MIME_AUDIO_MP4,
        self::MIME_WAV,
        self::MIME_X_WAV,
        self::MIME_MPEG,
        self::MIME_M4A,
        self::MIME_X_M4A,
    ];

    private const MIME_AUDIO_MP4 = 'audio/mp4';
    private const MIME_WAV = 'audio/wav';
    private const MIME_X_WAV = 'audio/x-wav';
    private const MIME_MPEG = 'audio/mpeg';
    private const MIME_M4A = 'audio/m4a';
    private const MIME_X_M4A = 'audio/x-m4a';

    case mimeMp4 = self::MIME_AUDIO_MP4;
    case mimeWaw = self::MIME_WAV;
    case mimeXWaw = self::MIME_X_WAV;
    case mimeMpeg = self::MIME_MPEG;
    case mimeM4a = self::MIME_M4A;
    case mimeXm4a = self::MIME_X_M4A;

    public static function getBrowserTypes(): array
    {
        return [
            self::mimeMpeg
        ];
    }
}
