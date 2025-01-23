<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AssetType: string implements EnumInterface
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

    public const AssetType Default = self::Image;

    public function getAllowedMimeChoices(): array
    {
        return match ($this) {
            self::Image => ImageMimeTypes::CHOICES,
            self::Video => VideoMimeTypes::CHOICES,
            self::Audio => AudioMimeTypes::CHOICES,
            self::Document => DocumentMimeTypes::CHOICES,
        };
    }

    public function isAllowedSiblingType(self $siblingType): bool
    {
        return match ($this) {
            self::Video => $siblingType->is(self::Audio),
            self::Audio => $siblingType->is(self::Video),
            default => false,
        };
    }
}
