<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\DistributionCategorySelect;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\DistributionCategoryOption\DistributionCategoryOptionManager;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategorySelect;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Exception;

final class DistributionCategorySelectManager extends AbstractManager
{
    public function __construct(
        private readonly DistributionCategoryOptionManager $distributionCategoryOptionManager,
    ) {
    }

    /**
     * @throws Exception
     */
    public function createForExtSystemService(
        string $serviceName,
        ExtSystem $extSystem,
        AssetType $type,
        bool $flush = true
    ): DistributionCategorySelect {
        $distributionCategorySelect = new DistributionCategorySelect();
        $distributionCategorySelect
            ->setServiceSlug($serviceName)
            ->setType($type)
            ->setExtSystem($extSystem)
        ;

        $this->trackCreation($distributionCategorySelect);
        $this->distributionCategoryOptionManager->createOptions($distributionCategorySelect);
        $this->entityManager->persist($distributionCategorySelect);
        $this->flush($flush);

        return $distributionCategorySelect;
    }

    /**
     * @throws Exception
     */
    public function update(
        DistributionCategorySelect $distributionCategorySelect,
        DistributionCategorySelect $newDistributionCategorySelect,
        bool $flush = true
    ): DistributionCategorySelect {
        $this->trackModification($distributionCategorySelect);
        $this->distributionCategoryOptionManager->updateOptions(
            $distributionCategorySelect,
            $newDistributionCategorySelect,
        );
        $this->flush($flush);

        return $distributionCategorySelect;
    }

    public function delete(DistributionCategorySelect $distributionCategorySelect, bool $flush = true): bool
    {
        $this->entityManager->remove($distributionCategorySelect);
        $this->distributionCategoryOptionManager->deleteOptions($distributionCategorySelect);
        $this->flush($flush);

        return true;
    }
}
