<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\DistributeMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DistributeHandler
{
    public function __construct(
        private readonly DistributionBroker $distributionBroker,
        private readonly DistributionRepository $distributionRepository,
        private readonly DamLogger $damLogger,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws SerializerException
     */
    public function __invoke(DistributeMessage $message): void
    {
        $distribution = $this->distributionRepository->find($message->getDistributionId());
        if (null === $distribution) {
            return;
        }

        match ($distribution->getStatus()) {
            DistributionProcessStatus::Waiting => $this->distributionBroker->toDistributing($distribution),
            DistributionProcessStatus::Distributing => $this->distributeAgain($distribution),
            DistributionProcessStatus::Distributed,
            DistributionProcessStatus::RemoteProcessing,
            DistributionProcessStatus::Failed => $this->invalidStatusToHandle($distribution),
        };
    }

    private function distributeAgain(Distribution $distribution): void
    {
        $this->damLogger->warning(
            DamLogger::NAMESPACE_DISTRIBUTION,
            sprintf(
                'Redistributing! (%s)',
                $distribution->getId(),
            )
        );

        $this->distributionBroker->toDistributing($distribution);
    }

    /**
     * @throws SerializerException
     */
    private function invalidStatusToHandle(Distribution $distribution): void
    {
        $this->damLogger->info(
            DamLogger::NAMESPACE_DISTRIBUTION,
            sprintf(
                'Invalid status to handle id (%s) status (%s)',
                $distribution->getId(),
                $distribution->getStatus()->toString()
            )
        );
    }
}
