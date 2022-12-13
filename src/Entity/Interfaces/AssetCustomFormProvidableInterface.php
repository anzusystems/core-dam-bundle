<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

interface AssetCustomFormProvidableInterface extends ExtSystemInterface
{
    public function getAssetType(): AssetType;
}
