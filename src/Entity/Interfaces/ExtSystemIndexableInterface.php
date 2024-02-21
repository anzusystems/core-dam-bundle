<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

use AnzuSystems\Contracts\Entity\Interfaces\IndexableInterface;

interface ExtSystemIndexableInterface extends ExtSystemInterface, IndexableInterface
{
    public static function getIndexName(): string;
}
