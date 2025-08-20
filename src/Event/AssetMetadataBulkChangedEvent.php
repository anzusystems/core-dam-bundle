<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use Doctrine\Common\Collections\Collection;

final readonly class AssetMetadataBulkChangedEvent
{
    public function __construct(
        /** @var Collection<int, Asset> */
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
