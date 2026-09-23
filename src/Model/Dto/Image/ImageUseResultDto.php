<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

/**
 * The file the caller must use from now on: either the one it asked for, or the copy that was taken over
 * into the target licence. `takenOverFromId` is the identity of the photo across licences.
 */
final class ImageUseResultDto
{
    #[Serialize]
    private string $imageFileId = App::EMPTY_STRING;

    #[Serialize]
    private int $licenceId = App::ZERO;

    #[Serialize]
    private bool $singleUse = false;

    #[Serialize]
    private ?string $takenOverFromId = null;

    #[Serialize]
    private bool $takenOver = false;

    public static function getInstance(AssetFile $assetFile, bool $takenOver): self
    {
        $attributes = $assetFile->getAssetAttributes();

        return (new self())
            ->setImageFileId((string) $assetFile->getId())
            ->setLicenceId((int) $assetFile->getLicence()->getId())
            ->setSingleUse($assetFile->getFlags()->isSingleUse())
            ->setTakenOverFromId($attributes->isTakenOver() ? $attributes->getTakenOverFromId() : null)
            ->setTakenOver($takenOver)
        ;
    }

    public function getImageFileId(): string
    {
        return $this->imageFileId;
    }

    public function setImageFileId(string $imageFileId): self
    {
        $this->imageFileId = $imageFileId;

        return $this;
    }

    public function getLicenceId(): int
    {
        return $this->licenceId;
    }

    public function setLicenceId(int $licenceId): self
    {
        $this->licenceId = $licenceId;

        return $this;
    }

    public function isSingleUse(): bool
    {
        return $this->singleUse;
    }

    public function setSingleUse(bool $singleUse): self
    {
        $this->singleUse = $singleUse;

        return $this;
    }

    public function getTakenOverFromId(): ?string
    {
        return $this->takenOverFromId;
    }

    public function setTakenOverFromId(?string $takenOverFromId): self
    {
        $this->takenOverFromId = $takenOverFromId;

        return $this;
    }

    public function isTakenOver(): bool
    {
        return $this->takenOver;
    }

    public function setTakenOver(bool $takenOver): self
    {
        $this->takenOver = $takenOver;

        return $this;
    }
}
