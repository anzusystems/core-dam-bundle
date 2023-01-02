<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use Doctrine\Common\Collections\Collection;

interface AssetFileInterface
{
    public function getAsset(): Asset;

    public function setAsset(Asset $asset): static;

    public function getSlots(): Collection;
}
