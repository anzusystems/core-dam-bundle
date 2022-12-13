<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

use AnzuSystems\CoreDamBundle\Entity\AssetHasFile;

interface AssetFileInterface
{
    public function getAsset(): AssetHasFile;

    public function setAsset(AssetHasFile $asset): static;
}
