<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CommonBundle\Helper\CollectionHelper as CommonCollectionHelper;
use Closure;
use Doctrine\Common\Collections\Collection;

final class CollectionHelper extends CommonCollectionHelper
{
    /**
     * @template TKey of array-key
     * @template T of object
     *
     * @param Collection<TKey, T> $collection
     * @param Closure(T): bool $compareFn
     *
     * @return T|null
     */
    public static function findFirst(Collection $collection, Closure $compareFn): mixed
    {
        foreach ($collection as $item) {
            if ($compareFn($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @template TKey of array-key
     * @template T
     *
     * @param iterable<T> $items
     * @param callable(T): TKey $keyFn
     *
     * @return array<TKey, T[]>
     */
    public static function groupBy(iterable $items, callable $keyFn): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$keyFn($item)][] = $item;
        }

        return $grouped;
    }
}
