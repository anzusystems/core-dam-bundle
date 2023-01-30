<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\RssDistribution;

use AnzuSystems\CoreDamBundle\Entity\RssDistribution;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;

final class RssDistributionFactory
{
    public function __construct(
        private readonly RssDistributionManager $distributionManager
    ) {
    }

    public function createFromRssItem(Item $item): RssDistribution
    {
        $distribution = (new RssDistribution())
            ->setRssUrl($item->getEnclosure()->getUrl())
            ->setExtId($item->getGuid());

        return $this->distributionManager->create($distribution, false);
    }
}
