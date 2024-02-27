<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Event\DistributionStatusEvent;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

final class DistributionStatusFacade
{
    use IndexManagerAwareTrait;

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly DistributionStatusManager $distributionStatusManager,
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
        try {
            $this->distributionStatusManager->beginTransaction();

            $this->distributionStatusManager->setStatus($distribution, $status);
            $this->indexManager->index($distribution);
            $this->distributionStatusManager->commit();
        } catch (Throwable $exception) {
            $this->distributionStatusManager->rollback();

            throw new RuntimeException('distribution_status_change_failed', 0, $exception);
        }

        $this->dispatcher->dispatch(new DistributionStatusEvent($distribution, $status));

        return $distribution;
    }
}
