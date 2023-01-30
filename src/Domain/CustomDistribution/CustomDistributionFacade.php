<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomDistribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionBodyBuilder;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\CustomDistribution;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Dto\CustomDistribution\CustomDistributionAdmDto;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use Doctrine\ORM\NonUniqueResultException;

final class CustomDistributionFacade
{
    public function __construct(
        private readonly DistributionBodyBuilder $distributionBodyBuilder,
        private readonly ModuleProvider $moduleProvider,
        private readonly EntityValidator $entityValidator,
        private readonly DistributionManager $distributionManager,
        private readonly DistributionBroker $distributionBroker,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    public function preparePayload(AssetFile $assetFile, string $distributionService): CustomDistribution
    {
        $module = $this->moduleProvider->provideCustomDistributionModule($distributionService);

        $distribution = new CustomDistribution();
        $distribution->setDistributionService($distributionService);

        $this->distributionBodyBuilder->setBaseFields($distributionService, $distribution);
        $this->distributionBodyBuilder->setWriterProperties(
            $distributionService,
            $assetFile->getAsset(),
            $distribution
        );

        return $module->addPayload($assetFile, $distribution);
    }

    /**
     * @throws ValidationException
     * @throws NonUniqueResultException
     */
    public function distribute(AssetFile $assetFile, CustomDistributionAdmDto $distributionDto): Distribution
    {
        $module = $this->moduleProvider->provideCustomDistributionModule($distributionDto->getDistributionService());

        $this->entityValidator->validateDto($distributionDto);
        $distribution = $module->createFromCustomDistributionDto($assetFile, $distributionDto);

        $distribution->setAssetId((string) $assetFile->getAsset()->getId());
        $distribution->setAssetFileId((string) $assetFile->getId());

        $this->distributionManager->setNotifyTo($distribution);
        $this->distributionManager->create($distribution);
        $this->distributionBroker->startDistribution($distribution);

        // todo write custom data from entity

        return $distribution;
    }
}
