<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AssetStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    public const array CHOICES = [
        self::DRAFT,
        self::WITH_FILE,
        self::DELETING,
    ];

    public const string DRAFT = 'draft';
    public const string WITH_FILE = 'with_file';
    public const string DELETING = 'deleting';

    case Draft = self::DRAFT;
    case WithFile = self::WITH_FILE;
    case Deleting = self::DELETING;

    public const AssetStatus Default = self::Draft;
}
