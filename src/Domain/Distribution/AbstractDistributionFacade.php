<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractDistributionFacade
{
    protected readonly DistributionBroker $distributionBroker;
    protected readonly EntityValidator $entityValidator;
    protected readonly DistributionConfigurationProvider $distributionConfigurationProvider;
    protected readonly DistributionManagerProvider $distributionManagerProvider;

    #[Required]
    public function setDistributionConfigurationProvider(DistributionConfigurationProvider $distributionConfigurationProvider): void
    {
        $this->distributionConfigurationProvider = $distributionConfigurationProvider;
    }

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
    public function setDistributionManagerProvider(DistributionManagerProvider $distributionManagerProvider): void
    {
        $this->distributionManagerProvider = $distributionManagerProvider;
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
        $this->distributionManagerProvider->get($distribution::class)->create($distribution);

        $this->distributionBroker->startDistribution($distribution);

        return $distribution;
    }

    /**
     * @throws ValidationException
     * @throws NonUniqueResultException
     */
    public function redistribute(Distribution $distribution, Distribution $newDistribution): Distribution
    {
        $config = $this->distributionConfigurationProvider->getDistributionService($distribution->getDistributionService());

        if (false === $distribution->getStatus()->in($config->getAllowedRedistributeStatuses())) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_STATE_TRANSACTION);
        }

        $this->entityValidator->validate($distribution);
        $this->distributionManagerProvider->get($distribution::class)->update($distribution, $newDistribution);

        $this->distributionBroker->redistribute($distribution);

        return $distribution;
    }
}
