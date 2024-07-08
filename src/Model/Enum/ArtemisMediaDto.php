<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum ArtemisMediaDto: string implements EnumInterface
{
    use BaseEnumTrait;

    public const array CHOICES = [
        self::IMAGE,
        self::VIDEO,
        self::AUDIO,
        self::DOCUMENT,
    ];

    public const string IMAGE = 'image';
    public const string VIDEO = 'video';
    public const string AUDIO = 'audio';
    public const string DOCUMENT = 'document';

    case Image = self::IMAGE;
    case Video = self::VIDEO;
    case Audio = self::AUDIO;
    case Document = self::DOCUMENT;

    public const ArtemisMediaDto Default = self::Image;

    public function getAllowedMimeChoices(): array
    {
        return match ($this) {
            self::Image => ImageMimeTypes::CHOICES,
            self::Video => VideoMimeTypes::CHOICES,
            self::Audio => AudioMimeTypes::CHOICES,
            self::Document => DocumentMimeTypes::CHOICES,
        };
    }
}
