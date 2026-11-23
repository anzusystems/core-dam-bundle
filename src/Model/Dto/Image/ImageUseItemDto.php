<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\When;

#[AppAssert\AssetCopyEqualExtSystem]
final class ImageUseItemDto
{
    #[Serialize(serializedName: 'imageFileId', handler: EntityIdHandler::class)]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[BaseAppAssert\NotEmptyId]
    private ImageFile $imageFile;

    #[Serialize(handler: EntityIdHandler::class)]
    #[When(
        expression: 'this.isForceTakeOver()',
        constraints: [new NotNull(message: ValidationException::ERROR_FIELD_EMPTY)],
    )]
    private ?AssetLicence $targetAssetLicence = null;

    #[Serialize]
    private bool $forceTakeOver = false;

    public function __construct()
    {
        $this->setImageFile(new ImageFile());
    }

    public function getImageFile(): ImageFile
    {
        return $this->imageFile;
    }

    public function setImageFile(ImageFile $imageFile): self
    {
        $this->imageFile = $imageFile;

        return $this;
    }

    public function getTargetAssetLicence(): ?AssetLicence
    {
        return $this->targetAssetLicence;
    }

    public function setTargetAssetLicence(?AssetLicence $targetAssetLicence): self
    {
        $this->targetAssetLicence = $targetAssetLicence;

        return $this;
    }

    public function isForceTakeOver(): bool
    {
        return $this->forceTakeOver;
    }

    public function setForceTakeOver(bool $forceTakeOver): self
    {
        $this->forceTakeOver = $forceTakeOver;

        return $this;
    }
}
