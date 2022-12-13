<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionStatusManager;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Exception\DistributionFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\DistributeMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\DistributionRemoteProcessingCheckMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionFailReason;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Messenger\MessageBusInterface;

final class DistributionBroker
{
    private const LOCK_PREFIX_NAME = 'distribution_';

    public function __construct(
        private readonly ResourceLocker $resourceLocker,
        private readonly MessageBusInterface $messageBus,
        private readonly DistributionRepository $repository,
        private readonly DistributionStatusManager $distributionStatusManager,
        private readonly ModuleProvider $moduleProvider,
        private readonly DamLogger $damLogger,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    public function startDistribution(Distribution $distribution): void
    {
        $this->resourceLocker->lock($this->getLockName($distribution));
        if ($this->repository->isNotBlockByNotFinished($distribution)) {
            $this->messageBus->dispatch(new DistributeMessage($distribution));
        }

        $this->resourceLocker->unLock($this->getLockName($distribution));
    }

    /**
     * @throws NonUniqueResultException
     */
    public function toDistributing(Distribution $distribution): void
    {
        $this->distributionStatusManager->toDistributing($distribution);
        $module = $this->moduleProvider->provideModule($distribution->getDistributionService(), true);

        try {
            $module->distribute($distribution);
        } catch (DistributionFailedException $e) {
            $distribution->setFailReason($e->getFailReason());
            $this->distributionStatusManager->toFailed($distribution);

            return;
        }

        if ($module instanceof RemoteProcessingDistributionModuleInterface) {
            $this->distributionStatusManager->toRemoteProcessing($distribution);
            $this->messageBus->dispatch(new DistributionRemoteProcessingCheckMessage($distribution));

            return;
        }

        $this->remoteProcessed($distribution);
    }

    /**
     * @throws NonUniqueResultException
     * @throws RemoteProcessingWaitingException
     * @throws SerializerException
     */
    public function checkRemoteProcessing(Distribution $distribution): void
    {
        $module = $this->moduleProvider->provideModule($distribution->getDistributionService(), true);

        if ($module instanceof RemoteProcessingDistributionModuleInterface) {
            try {
                $module->checkDistributionStatus($distribution);
                $this->remoteProcessed($distribution);
            } catch (RemoteProcessingFailedException $exception) {
                $this->damLogger->error(DamLogger::NAMESPACE_DISTRIBUTION, sprintf(
                    'Remote processing failed id: (%s) message: (%s)',
                    $distribution->getId(),
                    $exception->getMessage(),
                ));
                $distribution->setFailReason(DistributionFailReason::RemoteProcessFailed);
                $this->distributionStatusManager->toFailed($distribution);
            }
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    public function remoteProcessed(Distribution $distribution): void
    {
        $this->distributionStatusManager->toDistributed($distribution);
        foreach ($distribution->getBlocks() as $blockedDistribution) {
            $this->startDistribution($blockedDistribution);
        }
    }

    private function getLockName(Distribution $distribution): string
    {
        return self::LOCK_PREFIX_NAME . $distribution->getId();
    }
}
