<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Contracts\Service\Attribute\Required;

final class DistributionFacade
{
    private readonly DistributionBroker $distributionBroker;
    private readonly EntityValidator $entityValidator;
    private readonly DistributionManager $distributionManager;

    #[Required]
    public function setEntityValidator(EntityValidator $entityValidator): void
    {
        $this->entityValidator = $entityValidator;
    }

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
        $this->entityValidator->validate($distribution);

        $this->distributionManager->setNotifyTo($distribution);
        $this->distributionManager->create($distribution);
        $this->distributionBroker->startDistribution($distribution);

        return $distribution;
    }
}
