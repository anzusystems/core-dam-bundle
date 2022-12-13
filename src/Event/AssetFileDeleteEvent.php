<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final class AssetFileDeleteEvent
{
    public function __construct(
        protected readonly string $deleteId,
        protected readonly string $deleteAssetId,
        protected readonly AssetFile $assetFile,
        protected readonly AssetType $type,
        protected readonly DamUser $deletedBy,
    ) {
    }

    public function getDeleteAssetId(): string
    {
        return $this->deleteAssetId;
    }

    public function getDeleteId(): string
    {
        return $this->deleteId;
    }

    public function getAssetFile(): AssetFile
    {
        return $this->assetFile;
    }

    public function getType(): AssetType
    {
        return $this->type;
    }

    public function getDeletedBy(): DamUser
    {
        return $this->deletedBy;
    }
}
