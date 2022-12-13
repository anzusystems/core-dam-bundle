<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum AssetStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    public const CHOICES = [
        self::DRAFT,
        self::WITH_FILE,
        self::DELETING,
    ];

    public const DRAFT = 'draft';
    public const WITH_FILE = 'with_file';
    public const DELETING = 'deleting';

    case Draft = self::DRAFT;
    case WithFile = self::WITH_FILE;
    case Deleting = self::DELETING;

    public const Default = self::Draft;
}
