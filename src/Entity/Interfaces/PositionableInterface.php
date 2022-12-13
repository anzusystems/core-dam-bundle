<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

interface PositionableInterface
{
    public function setPosition(int $position): static;

    public function getPosition(): int;
}
