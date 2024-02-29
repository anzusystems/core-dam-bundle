<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum ImageMimeTypes: string implements EnumInterface
{
    use BaseEnumTrait;

    public const array CHOICES = [
        self::MIME_JPEG,
        self::MIME_PNG,
        self::MIME_WEBP,
        self::MIME_GIF,
        self::MIME_AVIF,
    ];

    private const string MIME_JPEG = 'image/jpeg';
    private const string MIME_PNG = 'image/png';
    private const string MIME_WEBP = 'image/webp';
    private const string MIME_GIF = 'image/gif';
    private const string MIME_AVIF = 'image/avif';

    case MimeJpeg = self::MIME_JPEG;
    case MimePng = self::MIME_PNG;
    case MimeWebp = self::MIME_WEBP;
    case MimeGif = self::MIME_GIF;
    // todo fix colors
//    case MimeAvif = self::MIME_AVIF;
}
