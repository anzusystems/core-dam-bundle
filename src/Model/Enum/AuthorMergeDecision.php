<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/**
 * Values of the decision column of the author merge CSV, written by people (product).
 * An empty decision is left on groups that need a person; it changes nothing, like `no change`.
 */
enum AuthorMergeDecision: string implements EnumInterface
{
    use BaseEnumTrait;

    case Main = 'main';
    case Variant = 'variant';
    case NoChange = 'no change';
    case Skip = 'skip';
    case DoNotMerge = 'do not merge';
    case Empty = '';

    public const AuthorMergeDecision Default = self::Empty;
}
