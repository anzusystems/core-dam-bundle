<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CoreDamBundle\App;

final class Math
{
    public static function getGreatestCommonDivisor(int $a, int $b): int
    {
        $large = max($a, $b);
        $small = min($a, $b);

        if (App::ZERO === $small) {
            return $large;
        }

        $remainder = $large % $small;

        return 0 === $remainder ? $small : self::getGreatestCommonDivisor($small, $remainder);
    }
}
