<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DistributionType: string implements EnumInterface
{
    use BaseEnumTrait;

    public const CHOICES = [
        self::YOUTUBE,
        self::JW_PLAYER,
    ];

    private const YOUTUBE = 'youtube';
    private const JW_PLAYER = 'jw_player';

    case youtube = self::YOUTUBE;
    case jwPlayer = self::JW_PLAYER;
}
