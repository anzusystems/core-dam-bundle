<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\JwDistribution;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;

final class JwDistributionManager extends AbstractManager
{
    public function create(JwDistribution $jwDistribution, bool $flush = true): JwDistribution
    {
        $this->trackCreation($jwDistribution);
        $this->entityManager->persist($jwDistribution);
        $this->flush($flush);

        return $jwDistribution;
    }
}
