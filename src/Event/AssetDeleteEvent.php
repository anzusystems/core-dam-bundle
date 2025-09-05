<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\DamUser;

final class AssetDeleteEvent
{
    public function __construct(
        private readonly string $deleteId,
        private readonly Asset $asset,
        private readonly DamUser $deletedBy,
    ) {
    }

    public function getDeleteId(): string
    {
        return $this->deleteId;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function getDeletedBy(): DamUser
    {
        return $this->deletedBy;
    }
}
