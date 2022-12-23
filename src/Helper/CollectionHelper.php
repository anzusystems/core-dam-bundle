<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CommonBundle\Helper\CollectionHelper as CommonCollectionHelper;
use Closure;
use Doctrine\Common\Collections\Collection;

final class CollectionHelper extends CommonCollectionHelper
{
    public static function findFirst(Collection $collection, Closure $compareFn): ?object
    {
        foreach ($collection as $item) {
            if ($compareFn($item)) {
                return $item;
            }
        }

        return null;
    }
}
