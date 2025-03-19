<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Symfony\Component\Validator\Constraints\NotBlank;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;

#[AppAssert\AssetCopyEqualExtSystem]
final class ImageCopyDto
{
    #[Serialize(handler: EntityIdHandler::class)]
    #[AppAssert\AssetProperties(assetType: AssetType::Image)]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[BaseAppAssert\NotEmptyId]
    private Asset $asset;

    #[Serialize(handler: EntityIdHandler::class)]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[BaseAppAssert\NotEmptyId]
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
