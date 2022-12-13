<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\DistributionApiParams;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomDistributionFilter;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use Doctrine\ORM\Exception\ORMException;

final class DistributionRepositoryDecorator
{
    public function __construct(
        private readonly DistributionRepository $distributionRepository,
    ) {
    }

    /**
     * @throws ORMException
     */
    public function findByApiParamsByAssetFile(ApiParams $apiParams, AssetFile $assetFile): ApiResponseList
    {
        return $this->distributionRepository->findByApiParams(
            apiParams: DistributionApiParams::applyAssetFileCustomFilter($apiParams, $assetFile),
            customFilters: [new CustomDistributionFilter()]
        );
    }

    /**
     * @throws ORMException
     */
    public function findByApiParamsByAsset(ApiParams $apiParams, Asset $asset): ApiResponseList
    {
        return $this->distributionRepository->findByApiParams(
            apiParams: DistributionApiParams::applyAssetCustomFilter($apiParams, $asset),
            customFilters: [new CustomDistributionFilter()]
        );
    }
}
