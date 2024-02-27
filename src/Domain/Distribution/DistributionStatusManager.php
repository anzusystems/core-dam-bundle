<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;

final class DistributionStatusManager extends AbstractManager
{
    public function __construct(
        private readonly DistributionManagerProvider $managerProvider,
    ) {
    }

    public function setStatus(
        Distribution $distribution,
        DistributionProcessStatus $status,
        bool $flush = true
    ): Distribution {
        $distribution->setStatus($status);
        $this->managerProvider
            ->get($distribution::class)
            ->updateExisting($distribution, $flush)
        ;

        return $distribution;
    }
}
