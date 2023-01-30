<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\RssDistribution;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\RssDistribution;

final class RssDistributionManager extends AbstractManager
{
    public function create(RssDistribution $rssDistribution, bool $flush = true): RssDistribution
    {
        $this->trackCreation($rssDistribution);
        $this->entityManager->persist($rssDistribution);
        $this->flush($flush);

        return $rssDistribution;
    }
}
