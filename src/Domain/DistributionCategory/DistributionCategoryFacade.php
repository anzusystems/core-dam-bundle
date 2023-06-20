<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\DistributionCategory;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategory;
use Exception;

final class DistributionCategoryFacade
{
    use ValidatorAwareTrait;

    public function __construct(
        private readonly DistributionCategoryManager $distributionCategoryManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(DistributionCategory $distributionCategorySelect): DistributionCategory
    {
        $this->validator->validate($distributionCategorySelect);

        return $this->distributionCategoryManager->create($distributionCategorySelect);
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function update(
        DistributionCategory $distributionCategory,
        DistributionCategory $newDistributionCategory,
    ): DistributionCategory {
        $this->validator->validate($newDistributionCategory, $distributionCategory);

        return $this->distributionCategoryManager->update(
            $distributionCategory,
            $newDistributionCategory
        );
    }
}
