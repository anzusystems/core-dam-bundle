<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Exception\RemoteProcessingWaitingException;
use AnzuSystems\CoreDamBundle\Messenger\Message\DistributionRemoteProcessingCheckMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DistributionRemoteProcessingCheckHandler
{
    public function __construct(
        private DistributionRepository $distributionRepository,
        private DistributionBroker $distributionBroker,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws NonUniqueResultException
     * @throws RemoteProcessingWaitingException
     * @throws SerializerException
     */
    public function __invoke(DistributionRemoteProcessingCheckMessage $message): void
    {
        $distribution = $this->distributionRepository->find($message->getDistributionId());

        if (null === $distribution || $distribution->getStatus()->isNot(DistributionProcessStatus::RemoteProcessing)) {
            return;
        }

        $this->distributionBroker->checkRemoteProcessing($distribution);
    }
}
