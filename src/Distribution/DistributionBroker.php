<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionStatusFacade;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Exception\DistributionFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingFailedException;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetRefreshPropertiesMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\DistributeMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\DistributionRemoteProcessingCheckMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionFailReason;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use Psr\Log\LoggerInterface;
use Throwable;

final class DistributionBroker
{
    use MessageBusAwareTrait;

    private const string LOCK_PREFIX_NAME = 'distribution_';

    public function __construct(
        private readonly ResourceLocker $resourceLocker,
        private readonly DistributionRepository $repository,
        private readonly DistributionStatusFacade $distributionStatusFacade,
        private readonly ModuleProvider $moduleProvider,
        private readonly DamLogger $damLogger,
        private readonly LoggerInterface $appLogger,
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
    public function redistribute(Distribution $distribution): void
    {
        $this->resourceLocker->lock($this->getLockName($distribution));
        $this->distributionStatusFacade->toDistributing($distribution);

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
        $this->distributionStatusFacade->toDistributing($distribution);
        $module = $this->moduleProvider->provideModule($distribution->getDistributionService(), true);

        try {
            $module->distribute($distribution);
        } catch (DistributionFailedException $exception) {
            $distribution->setFailReason($exception->getFailReason());
            $this->distributionStatusFacade->toFailed($distribution);

            return;
        } catch (Throwable $e) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_DISTRIBUTION,
                sprintf(
                    'Unexpected distribution error (%s)',
                    $e->getMessage()
                )
            );
            $this->appLogger->error($e->getMessage(), ['exception' => $e]);

            $distribution->setFailReason(DistributionFailReason::Unknown);
            $this->distributionStatusFacade->toFailed($distribution);
        }

        if ($module instanceof RemoteProcessingDistributionModuleInterface) {
            $this->distributionStatusFacade->toRemoteProcessing($distribution);
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
                $this->appLogger->error($exception->getMessage(), ['exception' => $exception]);
                $distribution->setFailReason(DistributionFailReason::RemoteProcessFailed);
                $this->distributionStatusFacade->toFailed($distribution);
            }
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    public function remoteProcessed(Distribution $distribution): void
    {
        $this->distributionStatusFacade->toDistributed($distribution);
        $this->messageBus->dispatch(new AssetRefreshPropertiesMessage($distribution->getAssetId()));

        foreach ($distribution->getBlocks() as $blockedDistribution) {
            $this->startDistribution($blockedDistribution);
        }
    }

    private function getLockName(Distribution $distribution): string
    {
        return self::LOCK_PREFIX_NAME . $distribution->getId();
    }
}
