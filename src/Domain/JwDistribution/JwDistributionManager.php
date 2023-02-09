<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\JwDistribution;

use AnzuSystems\CoreDamBundle\Domain\Distribution\AbstractDistributionManager;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;

final class JwDistributionManager extends AbstractDistributionManager
{
    /**
     * @param JwDistribution $distribution
     * @param JwDistribution $newDistribution
     */
    public function update(Distribution $distribution, Distribution $newDistribution, bool $flush = true): Distribution
    {
        $this->trackModification($distribution);
        $distribution->getTexts()
            ->setTitle($newDistribution->getTexts()->getTitle())
            ->setDescription($newDistribution->getTexts()->getDescription())
            ->setKeywords($newDistribution->getTexts()->getKeywords())
            ->setAuthor($newDistribution->getTexts()->getAuthor());

        $this->flush($flush);

        return $distribution;
    }

    public static function getDefaultKeyName(): string
    {
        return JwDistribution::class;
    }
}
