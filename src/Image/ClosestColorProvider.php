<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image;

use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\ValueObject\Color;

final class ClosestColorProvider
{
    private const int MAX_DISTANCE = 9_999;

    public function __construct(
        private readonly array $colorSet
    ) {
    }

    public function provideClosestColor(Color $fromColor): Color
    {
        $dist = self::MAX_DISTANCE;
        $color = null;
        foreach ($this->colorSet as $baseColor) {
            $newDist = $fromColor->getDist(...$baseColor);
            if ($newDist < $dist) {
                $dist = $newDist;
                $color = $baseColor;
            }
        }

        if ($color) {
            return new Color(...$color);
        }

        throw new DomainException("Closest color for color ({$fromColor}) not found");
    }
}
