<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

interface ExtSystemInterface
{
    public function getExtSystem(): ExtSystem;
}
