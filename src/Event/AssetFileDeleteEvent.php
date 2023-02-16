<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final readonly class AssetFileDeleteEvent
{
    public function __construct(
        protected string $deleteId,
        protected string $deleteAssetId,
        protected AssetFile $assetFile,
        protected AssetType $type,
        protected DamUser $deletedBy,
        protected array $roiPositions,
        protected string $extSystem,
    ) {
    }

    public function getRoiPositions(): array
    {
        return $this->roiPositions;
    }

    public function getExtSystem(): string
    {
        return $this->extSystem;
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
