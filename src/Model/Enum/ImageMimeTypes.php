<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum ImageMimeTypes: string implements EnumInterface
{
    use BaseEnumTrait;

    public const CHOICES = [
        self::MIME_JPEG,
        self::MIME_PNG,
        self::MIME_WEBP,
        self::MIME_GIF,
    ];

    private const MIME_JPEG = 'image/jpeg';
    private const MIME_PNG = 'image/png';
    private const MIME_WEBP = 'image/webp';
    private const MIME_GIF = 'image/gif';

    case MimeJpeg = self::MIME_JPEG;
    case MimePng = self::MIME_PNG;
    case MimeWebp = self::MIME_WEBP;
    case MimeGif = self::MIME_GIF;
}
