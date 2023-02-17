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

    case Private = self::PRIVATE;
    case Public = self::PUBLIC;
    case Unlisted = self::UNLISTED;
    case Dynamic = self::DYNAMIC;

    public const Default = self::Private;
}
