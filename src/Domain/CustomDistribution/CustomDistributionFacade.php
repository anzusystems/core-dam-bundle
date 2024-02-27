<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomDistribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Distribution\DistributionAdapterInterface;
use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionBodyBuilder;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionManagerProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\CustomDistribution\CustomDistributionAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CustomDistributionFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly DistributionBodyBuilder $distributionBodyBuilder,
        private readonly ModuleProvider $moduleProvider,
        private readonly DistributionBroker $distributionBroker,
        private readonly DistributionManagerProvider $managerProvider,
        private readonly DistributionConfigurationProvider $distributionConfigurationProvider,
        private readonly AssetFileRepository $assetFileRepository,
        private readonly DistributionManagerProvider $distributionManagerProvider,
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
        $this->distributionBodyBuilder->setBaseFields($assetFile, $distributionService, $distribution);
        $this->distributionBodyBuilder->setWriterProperties(
            $distributionService,
            $assetFile->getAsset(),
            $distribution
        );

        return $distribution;
    }

    public function delete(Distribution $distribution): void
    {
        if (false === $distribution->getStatus()->in([DistributionProcessStatus::Waiting, DistributionProcessStatus::Failed])) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_STATE_TRANSACTION);
        }

        if (false === $distribution->getBlocks()->isEmpty()) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IS_BLOCKING_ERROR);
        }

        $this->managerProvider->get($distribution::class)->delete($distribution);
    }

    /**
     * @throws ValidationException
     * @throws NonUniqueResultException
     */
    public function distribute(AssetFile $assetFile, CustomDistributionAdmDto $distributionDto): Distribution
    {
        $this->validator->validate($distributionDto);
        $distribution = $this->getAdapter($distributionDto)->createDistributionEntity($assetFile, $distributionDto);
        $this->managerProvider->get($distribution::class)->create($distribution);

        $this->distributionBroker->startDistribution($distribution);

        return $distribution;
    }

    /**
     * @throws ValidationException
     * @throws NonUniqueResultException
     */
    public function redistribute(Distribution $distribution, CustomDistributionAdmDto $newDistributionDto): Distribution
    {
        $config = $this->distributionConfigurationProvider->getDistributionService($distribution->getDistributionService());

        if (false === $distribution->getStatus()->in($config->getAllowedRedistributeStatuses())) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_STATE_TRANSACTION);
        }

        $assetFile = $this->assetFileRepository->find($distribution->getAssetFileId());
        if (null === $assetFile) {
            throw new NotFoundHttpException(sprintf('Asset file id (%s) not found', $distribution->getAssetFileId()));
        }

        $newDistribution = $this->getAdapter($newDistributionDto)->createDistributionEntity($assetFile, $newDistributionDto);
        $this->distributionManagerProvider->get($distribution::class)->update($distribution, $newDistribution);
        $this->distributionBroker->redistribute($distribution);

        return $distribution;
    }

    private function getAdapter(CustomDistributionAdmDto $distributionDto): DistributionAdapterInterface
    {
        $adapter = $this->moduleProvider->provideAdapter($distributionDto->getDistributionService());
        if ($adapter) {
            return $adapter;
        }

        throw new BadRequestHttpException('Service not valid for custom distribution');
    }
}
