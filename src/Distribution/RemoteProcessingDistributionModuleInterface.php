<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

use AnzuSystems\CoreDamBundle\Entity\Distribution;

interface RemoteProcessingDistributionModuleInterface
{
    public function checkDistributionStatus(Distribution $distribution): void;
}
