<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\AssetTypeApiParams;
use AnzuSystems\CoreDamBundle\ApiFilter\ExySystemApiParams;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomAssetTypeFilter;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomExtSystemFilter;
use AnzuSystems\CoreDamBundle\Repository\DistributionCategorySelectRepository;
use Doctrine\ORM\Exception\ORMException;

final class DistributionCategorySelectAdmRepositoryDecorator
{
    public function __construct(
        private readonly DistributionCategorySelectRepository $distributionCategorySelectRepository,
    ) {
    }

    /**
     * @throws ORMException
     */
    public function findByApiParams(
        ApiParams $apiParams,
        ExtSystem $extSystem,
        AssetType $type = null,
    ): ApiResponseList {
        $customFilters = [new CustomExtSystemFilter()];
        $apiParams = ExySystemApiParams::applyCustomFilter($apiParams, $extSystem);
        if ($type instanceof AssetType) {
            $customFilters[] = new CustomAssetTypeFilter();
            $apiParams = AssetTypeApiParams::applyCustomFilter($apiParams, $type);
        }

        return $this->distributionCategorySelectRepository->findByApiParams(
            apiParams: $apiParams,
            customFilters: $customFilters,
        );
    }
}
