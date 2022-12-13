<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\ExySystemApiParams;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomExtSystemFilter;
use AnzuSystems\CoreDamBundle\Repository\DistributionCategoryRepository;
use Doctrine\ORM\Exception\ORMException;

final class DistributionCategoryAdmRepositoryDecorator
{
    public function __construct(
        private readonly DistributionCategoryRepository $distributionCategoryRepository,
    ) {
    }

    /**
     * @throws ORMException
     */
    public function findByApiParams(
        ApiParams $apiParams,
        ExtSystem $extSystem,
    ): ApiResponseList {
        return $this->distributionCategoryRepository->findByApiParams(
            apiParams: ExySystemApiParams::applyCustomFilter($apiParams, $extSystem),
            customFilters: [new CustomExtSystemFilter()],
        );
    }
}
