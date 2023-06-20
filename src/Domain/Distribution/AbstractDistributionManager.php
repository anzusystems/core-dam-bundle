<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Distribution;

abstract class AbstractDistributionManager extends AbstractManager implements DistributionManagerInterface
{
    public function create(Distribution $distribution, bool $flush = true): Distribution
    {
        $this->trackCreation($distribution);
        $this->entityManager->persist($distribution);
        $this->flush($flush);

        return $distribution;
    }

    public function updateExisting(Distribution $distribution, bool $flush = true): Distribution
    {
        $this->trackModification($distribution);
        $this->flush($flush);

        return $distribution;
    }

    public function delete(Distribution $distribution, bool $flush = true): bool
    {
        $this->entityManager->remove($distribution);
        $this->flush($flush);

        return true;
    }
}
