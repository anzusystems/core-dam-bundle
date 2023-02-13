<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\DistributionCategorySelect;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategorySelect;
use Exception;

final class DistributionCategorySelectFacade
{
    use ValidatorAwareTrait;

    public function __construct(
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
        $this->validator->validate($newDistributionCategorySelect, $distributionCategorySelect);

        return $this->distributionCategorySelectManager->update(
            $distributionCategorySelect,
            $newDistributionCategorySelect
        );
    }
}
