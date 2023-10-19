<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\YoutubeDistribution;

use AnzuSystems\CoreDamBundle\Domain\Distribution\AbstractDistributionManager;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;

final class YoutubeAbstractDistributionManager extends AbstractDistributionManager
{
    /**
     * @param YoutubeDistribution $distribution
     * @param YoutubeDistribution $newDistribution
     */
    public function update(Distribution $distribution, Distribution $newDistribution, bool $flush = true): Distribution
    {
        $this->trackModification($distribution);
        $distribution->getTexts()
            ->setTitle($newDistribution->getTexts()->getTitle())
            ->setDescription($newDistribution->getTexts()->getDescription())
            ->setKeywords($newDistribution->getTexts()->getKeywords());
        $distribution->getFlags()
            ->setForKids($newDistribution->getFlags()->isForKids())
            ->setEmbeddable($newDistribution->getFlags()->isEmbeddable())
            ->setNotifySubscribers($newDistribution->getFlags()->isNotifySubscribers());
        $distribution->setPublishAt($newDistribution->getPublishAt());
        $distribution->setLanguage($newDistribution->getLanguage());

        $this->flush($flush);

        return $distribution;
    }

    public static function getDefaultKeyName(): string
    {
        return YoutubeDistribution::class;
    }
}
