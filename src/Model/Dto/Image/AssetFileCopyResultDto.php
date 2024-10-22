<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyResult;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class AssetFileCopyResultDto
{
    #[Serialize(handler: EntityIdHandler::class)]
    private Asset $asset;

    #[Serialize(handler: EntityIdHandler::class)]
    private ?Asset $targetAsset = null;

    #[Serialize(handler: EntityIdHandler::class)]
    private ?AssetFile $targetMainFile = null;

    #[Serialize(handler: EntityIdHandler::class)]
    private AssetLicence $targetAssetLicence;

    #[Serialize]
    private AssetFileCopyResult $result;

    #[Serialize(handler: EntityIdHandler::class)]
    private Collection $assetConflicts;

    public static function create(
        Asset $asset,
        AssetLicence $targetAssetLicence,
        AssetFileCopyResult $result,
        ?AssetFile $targetMainFile = null,
        ?Asset $targetAsset = null,
        array $assetConflicts = []
    ): self {
        return (new self())
            ->setAsset($asset)
            ->setTargetAssetLicence($targetAssetLicence)
            ->setResult($result)
            ->setTargetAsset($targetAsset)
            ->setTargetMainFile($targetMainFile)
            ->setAssetConflicts(new ArrayCollection($assetConflicts))
        ;
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

    #[Serialize(handler: EntityIdHandler::class)]
    public function getTargetMainFile(): ?AssetFile
    {
        return $this->targetMainFile;
    }

    public function setTargetMainFile(?AssetFile $targetMainFile): self
    {
        $this->targetMainFile = $targetMainFile;

        return $this;
    }

    public function getResult(): AssetFileCopyResult
    {
        return $this->result;
    }

    public function setResult(AssetFileCopyResult $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getAssetConflicts(): Collection
    {
        return $this->assetConflicts;
    }

    public function setAssetConflicts(Collection $assetConflicts): self
    {
        $this->assetConflicts = $assetConflicts;

        return $this;
    }

    public function getTargetAsset(): ?Asset
    {
        return $this->targetAsset;
    }

    public function setTargetAsset(?Asset $targetAsset): self
    {
        $this->targetAsset = $targetAsset;

        return $this;
    }
}
