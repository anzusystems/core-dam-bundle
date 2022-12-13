<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\YoutubeDistribution;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;

final class YoutubeDistributionManager extends AbstractManager
{
    public function create(YoutubeDistribution $youtubeDistribution, bool $flush = true): YoutubeDistribution
    {
        $this->trackCreation($youtubeDistribution);
        $this->entityManager->persist($youtubeDistribution);
        $this->flush($flush);

        return $youtubeDistribution;
    }
}
