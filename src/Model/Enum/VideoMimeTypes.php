<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum VideoMimeTypes: string implements EnumInterface
{
    use BaseEnumTrait;

    public const array CHOICES = [
        self::MIME_MP4,
        self::MIME_FLV,
        self::MIME_3GP,
        self::MIME_X_MSVIDEO,
        self::MIME_MS_VIDEO,
        self::MIME_AVI,
        self::MIME_MOV,
        self::MIME_WMW,
        self::MIME_WMW_ASF,
    ];

    private const string MIME_MP4 = 'video/mp4';
    private const string MIME_FLV = 'video/x-flv';
    private const string MIME_3GP = 'video/3gpp';
    private const string MIME_X_MSVIDEO = 'video/x-msvideo';
    private const string MIME_MS_VIDEO = 'video/msvideo';
    private const string MIME_AVI = 'video/avi';
    private const string MIME_MOV = 'video/quicktime';
    private const string MIME_WMW = 'video/x-ms-wmv';
    private const string MIME_WMW_ASF = 'video/x-ms-asf';

    case MimeMp4 = self::MIME_MP4;
    case MimeXFlv = self::MIME_FLV;
    case Mime3gp = self::MIME_3GP;
    case MimeXMsVideo = self::MIME_X_MSVIDEO;
    case MimeMsVideo = self::MIME_MS_VIDEO;
    case MimeAvi = self::MIME_AVI;
    case MimeMov = self::MIME_MOV;
    case MimeWmw = self::MIME_WMW;
    case MimeAsf = self::MIME_WMW_ASF;
}
