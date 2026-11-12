<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/**
 * Values of the decision column of the author merge CSV, written by people (product), so they stay Slovak.
 * An empty decision is left on groups that need a person; it changes nothing, like `bez zmeny`.
 */
enum AuthorMergeDecision: string implements EnumInterface
{
    use BaseEnumTrait;

    case Main = 'hlavny';
    case Variant = 'podriadeny';
    case NoChange = 'bez zmeny';
    case Skip = 'vynechat';
    case DoNotMerge = 'nezlucovat';
    case Empty = '';

    public const AuthorMergeDecision Default = self::Empty;
}
