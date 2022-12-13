<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\DistributionCategorySelect;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategorySelect;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use Exception;

final class DistributionCategorySelectFacade
{
    public function __construct(
        private readonly EntityValidator $entityValidator,
        private readonly DistributionCategorySelectManager $distributionCategorySelectManager,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function update(
        DistributionCategorySelect $distributionCategorySelect,
        DistributionCategorySelect $newDistributionCategorySelect,
    ): DistributionCategorySelect {
        $this->entityValidator->validate($newDistributionCategorySelect, $distributionCategorySelect);

        return $this->distributionCategorySelectManager->update(
            $distributionCategorySelect,
            $newDistributionCategorySelect
        );
    }
}
