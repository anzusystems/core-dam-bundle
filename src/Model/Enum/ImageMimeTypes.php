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

    case mimeJpeg = self::MIME_JPEG;
    case mimePng = self::MIME_PNG;
    case mimeWebp = self::MIME_WEBP;
    case mimeGif = self::MIME_GIF;
}
