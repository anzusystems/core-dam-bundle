<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomDistribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionBodyBuilder;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Dto\CustomDistribution\CustomDistributionAdmDto;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
    public function preparePayload(AssetFile $assetFile, string $distributionService): Distribution
    {
        $adapter = $this->moduleProvider->provideAdapter($distributionService);
        if (null === $adapter) {
            throw new BadRequestHttpException('Service not valid for custom distribution');
        }

        $distribution = $adapter->preparePayload($assetFile, $distributionService);
        $this->distributionBodyBuilder->setBaseFields($distributionService, $distribution);
        $this->distributionBodyBuilder->setWriterProperties(
            $distributionService,
            $assetFile->getAsset(),
            $distribution
        );

        return $distribution;
    }

    /**
     * @throws ValidationException
     * @throws NonUniqueResultException
     */
    public function distribute(AssetFile $assetFile, CustomDistributionAdmDto $distributionDto): Distribution
    {
        $this->entityValidator->validateDto($distributionDto);
        $adapter = $this->moduleProvider->provideAdapter($distributionDto->getDistributionService());

        if (null === $adapter) {
            throw new BadRequestHttpException('Service not valid for custom distribution');
        }

        $distribution = $adapter->createDistributionEntity($assetFile, $distributionDto);
        $distribution->setAssetId((string) $assetFile->getAsset()->getId());
        $distribution->setAssetFileId((string) $assetFile->getId());

        $this->distributionManager->setNotifyTo($distribution);
        $this->distributionManager->create($distribution);
        $this->distributionBroker->startDistribution($distribution);

        return $distribution;
    }
}
