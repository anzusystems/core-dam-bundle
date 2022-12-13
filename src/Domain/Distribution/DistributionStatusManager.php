<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Event\DistributionStatusEvent;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class DistributionStatusManager extends DistributionManager
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function toDistributing(Distribution $distribution): Distribution
    {
        return $this->setStatus($distribution, DistributionProcessStatus::Distributing);
    }

    public function toRemoteProcessing(Distribution $distribution): Distribution
    {
        return $this->setStatus($distribution, DistributionProcessStatus::RemoteProcessing);
    }

    public function toDistributed(Distribution $distribution): Distribution
    {
        return $this->setStatus($distribution, DistributionProcessStatus::Distributed);
    }

    public function toFailed(Distribution $distribution): Distribution
    {
        return $this->setStatus($distribution, DistributionProcessStatus::Failed);
    }

    private function setStatus(Distribution $distribution, DistributionProcessStatus $status): Distribution
    {
        $distribution->setStatus($status);
        $this->updateExisting($distribution);

        $this->dispatcher->dispatch(
            new DistributionStatusEvent(
                $distribution,
                $status
            )
        );

        return $distribution;
    }
}
