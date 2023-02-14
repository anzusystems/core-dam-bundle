<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use Exception;

class AssetSlotUsedException extends Exception
{
    private const ERROR_MESSAGE = 'asset_slot_used';

    public function __construct(
        private readonly AssetFile $assetFile,
        private readonly string $position
    ) {
        parent::__construct(self::ERROR_MESSAGE);
    }

    public function getAssetFile(): AssetFile
    {
        return $this->assetFile;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function getDetail(): string
    {
        return sprintf(
            'There is already file (%s) in asset (%s) at position (%s)',
            (string) $this->getAssetFile()->getId(),
            (string) $this->getAssetFile()->getAsset()->getId(),
            $this->position,
        );
    }
}
