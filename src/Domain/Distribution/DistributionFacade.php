<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Contracts\Service\Attribute\Required;

class DistributionFacade
{
    use ValidatorAwareTrait;

    protected readonly DistributionBroker $distributionBroker;
    protected readonly DistributionManager $distributionManager;

    #[Required]
    public function setDistributionBroker(DistributionBroker $distributionBroker): void
    {
        $this->distributionBroker = $distributionBroker;
    }

    #[Required]
    public function setDistributionManager(DistributionManager $distributionManager): void
    {
        $this->distributionManager = $distributionManager;
    }

    /**
     * @throws ValidationException
     * @throws NonUniqueResultException
     */
    public function distribute(AssetFile $assetFile, Distribution $distribution): Distribution
    {
        $distribution->setAssetId((string) $assetFile->getAsset()->getId());
        $distribution->setAssetFileId((string) $assetFile->getId());
        $this->validator->validate($distribution);

        $this->distributionManager->setNotifyTo($distribution);
        $this->distributionManager->create($distribution);
        $this->distributionBroker->startDistribution($distribution);

        return $distribution;
    }

    /**
     * @throws ValidationException
     * @throws NonUniqueResultException
     */
    public function redistribute(Distribution $distribution): Distribution
    {
        if ($distribution->getStatus()->is(DistributionProcessStatus::Distributed)) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_STATE_TRANSACTION);
        }
        $this->validator->validate($distribution);

        $this->distributionManager->setNotifyTo($distribution);
        $this->distributionManager->updateExisting($distribution);
        $this->distributionBroker->redistribute($distribution);

        return $distribution;
    }
}
