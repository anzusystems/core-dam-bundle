<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\DistributionCategory;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategory;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use Exception;

final class DistributionCategoryFacade
{
    public function __construct(
        private readonly EntityValidator $entityValidator,
        private readonly DistributionCategoryManager $distributionCategoryManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(DistributionCategory $distributionCategorySelect): DistributionCategory
    {
        $this->entityValidator->validate($distributionCategorySelect);

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
        $this->entityValidator->validate($newDistributionCategory, $distributionCategory);

        return $this->distributionCategoryManager->update(
            $distributionCategory,
            $newDistributionCategory
        );
    }
}
