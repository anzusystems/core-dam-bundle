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

/**
 * One photo of an {@see ImageUseRequestDto} batch: which file to use and, where the licence forbids using
 * it as is, which licence to take it over into.
 */
#[AppAssert\AssetCopyEqualExtSystem]
final class ImageUseItemDto
{
    #[Serialize(serializedName: 'imageFileId', handler: EntityIdHandler::class)]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[BaseAppAssert\NotEmptyId]
    private ImageFile $imageFile;

    /**
     * Null asks whether the image may be used as it is: nothing is copied and a licence that forbids
     * direct use is reported instead. Clients that cannot name a target licence yet use this.
     */
    #[Serialize(handler: EntityIdHandler::class)]
    private ?AssetLicence $targetAssetLicence = null;

    /**
     * Take the image over even from a licence that allows direct use (manual "copy to licence").
     */
    #[Serialize]
    private bool $force = false;

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

    public function isForce(): bool
    {
        return $this->force;
    }

    public function setForce(bool $force): self
    {
        $this->force = $force;

        return $this;
    }
}
