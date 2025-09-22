<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use Doctrine\Common\Collections\Collection;

final readonly class AssetChangedEvent
{
    public function __construct(
        /**
         * @var Collection<int, Asset>
         */
        private Collection $affectedAssets,
    ) {
    }

    /**
     * @return Collection<int, Asset>
     */
    public function getAffectedAssets(): Collection
    {
        return $this->affectedAssets;
    }
}
