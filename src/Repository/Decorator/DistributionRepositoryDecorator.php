<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\DistributionApiParams;
use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomDistributionFilter;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use Doctrine\ORM\Exception\ORMException;

final class DistributionRepositoryDecorator
{
    public function __construct(
        private readonly DistributionRepository $distributionRepository,
        private readonly ModuleProvider $moduleProvider,
    ) {
    }

    public function decorate(Distribution $distribution): mixed
    {
        $adapter = $this->moduleProvider->provideAdapter($distribution->getDistributionService());
        if ($adapter) {
            return $adapter->decorateDistribution($distribution);
        }

        return $distribution;
    }

    /**
     * @throws ORMException
     */
    public function findByApiParamsByAssetFile(ApiParams $apiParams, AssetFile $assetFile): ApiResponseList
    {
        $responseList = $this->distributionRepository->findByApiParams(
            apiParams: DistributionApiParams::applyAssetFileCustomFilter($apiParams, $assetFile),
            customFilters: [new CustomDistributionFilter()]
        );

        return $responseList->setData(
            $this->mapToDecorators($responseList->getData())
        );
    }

    /**
     * @throws ORMException
     */
    public function findByApiParamsByAsset(ApiParams $apiParams, Asset $asset): ApiResponseList
    {
        $responseList = $this->distributionRepository->findByApiParams(
            apiParams: DistributionApiParams::applyAssetCustomFilter($apiParams, $asset),
            customFilters: [new CustomDistributionFilter()]
        );

        return $responseList->setData(
            $this->mapToDecorators($responseList->getData())
        );
    }

    /**
     * @param array<int, Distribution> $data
     */
    private function mapToDecorators(array $data): array
    {
        return array_map(
            function (Distribution $distribution): mixed {
                return $this->decorate($distribution);
            },
            $data
        );
    }
}
