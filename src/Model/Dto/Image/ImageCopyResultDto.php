<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\CustomDataInterface;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyResult;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class ImageCopyResultDto
{
    #[Serialize(handler: EntityIdHandler::class)]
    private Asset $asset;

    #[Serialize(handler: EntityIdHandler::class)]
    private ?Asset $foundAsset = null;

    #[Serialize(handler: EntityIdHandler::class)]
    private ?AssetFile $foundMainFile = null;

    #[Serialize(handler: EntityIdHandler::class)]
    private AssetLicence $targetAssetLicence;

    #[Serialize]
    private AssetFileCopyResult $result;

    #[Serialize(handler: EntityIdHandler::class)]
    private Collection $assetConflicts;

    public function __construct()
    {
        $this->setAsset(new Asset());
        $this->setTargetAssetLicence(new AssetLicence());
    }

    public static function create(
        Asset $asset,
        AssetLicence $targetAssetLicence,
        AssetFileCopyResult $result,
        ?AssetFile $mainAssetFile = null,
        ?Asset $mainAsset = null,
        array $assetConflicts = []
    ): ImageCopyResultDto
    {
        return (new self)
            ->setAsset($asset)
            ->setTargetAssetLicence($targetAssetLicence)
            ->setResult($result)
            ->setFoundAsset($mainAsset)
            ->setFoundMainFile($mainAssetFile)
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

    public function getFoundMainFile(): ?AssetFile
    {
        return $this->foundMainFile;
    }

    public function setFoundMainFile(?AssetFile $foundMainFile): self
    {
        $this->foundMainFile = $foundMainFile;
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

    public function getFoundAsset(): ?Asset
    {
        return $this->foundAsset;
    }

    public function setFoundAsset(?Asset $foundAsset): self
    {
        $this->foundAsset = $foundAsset;
        return $this;
    }
}
