<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;

interface AssetLicenceInterface
{
    public function getLicence(): AssetLicence;
}
