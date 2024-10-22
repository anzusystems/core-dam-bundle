<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

// TODO SAME EXT SYSTEM!
final class ImageCopyDto
{
    // todo assert same ext system!
    // Todo Assert copy type
    #[Serialize(handler: EntityIdHandler::class)]
    private Asset $asset;

    #[Serialize(handler: EntityIdHandler::class)]
    private AssetLicence $targetAssetLicence;

    public function __construct()
    {
        $this->setAsset(new Asset());
        $this->setTargetAssetLicence(new AssetLicence());
    }

    public function getTargetAssetLicence(): AssetLicence
    {
        return $this->targetAssetLicence;
    }

    public function setTargetAssetLicence(AssetLicence $targetAssetLicence): self
    {
        $this->targetAssetLicence = $targetAssetLicence;

        return $this;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): self
    {
        $this->asset = $asset;

        return $this;
    }
}
