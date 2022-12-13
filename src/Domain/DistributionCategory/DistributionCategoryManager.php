<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\DistributionCategory;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategory;

final class DistributionCategoryManager extends AbstractManager
{
    public function create(DistributionCategory $distributionCategory, bool $flush = true): DistributionCategory
    {
        $this->trackCreation($distributionCategory);
        $this->entityManager->persist($distributionCategory);
        $this->flush($flush);

        return $distributionCategory;
    }

    public function update(
        DistributionCategory $distributionCategory,
        DistributionCategory $newDistributionCategory,
        bool $flush = true,
    ): DistributionCategory {
        $this->trackModification($distributionCategory);
        $distributionCategory
            ->setName($newDistributionCategory->getName())
            ->setType($newDistributionCategory->getType())
        ;
        $this->colUpdate(
            oldCollection: $distributionCategory->getSelectedOptions(),
            newCollection: $newDistributionCategory->getSelectedOptions(),
        );
        $this->flush($flush);

        return $distributionCategory;
    }

    public function delete(DistributionCategory $distributionCategory, bool $flush = true): bool
    {
        $this->entityManager->remove($distributionCategory);
        $this->flush($flush);

        return true;
    }
}
