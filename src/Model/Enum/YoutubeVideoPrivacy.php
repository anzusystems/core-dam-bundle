<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum YoutubeVideoPrivacy: string implements EnumInterface
{
    use BaseEnumTrait;

    public const PRIVATE = 'private';
    public const PUBLIC = 'public';
    public const UNLISTED = 'unlisted';
    public const DYNAMIC = 'dynamic';

    case private = self::PRIVATE;
    case public = self::PUBLIC;
    case unlisted = self::UNLISTED;
    case dynamic = self::DYNAMIC;

    public const Default = self::private;
}
