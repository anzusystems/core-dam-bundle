<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

/**
 * Holds data needed for cache purge
 */
final readonly class AssetFileDeleteEvent
{
    public function __construct(
        private string $deleteId,
        private string $deleteAssetId,
        private AssetFile $assetFile,
        private AssetType $type,
        private DamUser $deletedBy,
        private array $roiPositions,
        private string $extSystem,
        private array $routePaths,
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

    /**
     * @return string[]
     */
    public function getRoutePaths(): array
    {
        return $this->routePaths;
    }
}
