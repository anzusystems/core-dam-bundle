<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;

final class DistributionStatusEvent
{
    public function __construct(
        private readonly Distribution $distribution,
        private readonly DistributionProcessStatus $status
    ) {
    }

    public function getDistribution(): Distribution
    {
        return $this->distribution;
    }

    public function getStatus(): DistributionProcessStatus
    {
        return $this->status;
    }
}
