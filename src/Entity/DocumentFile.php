<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\Entity\Embeds\DocumentAttributes;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\DocumentFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentFileRepository::class)]
class DocumentFile extends AssetFile
{
    #[ORM\Embedded(class: DocumentAttributes::class)]
    private DocumentAttributes $attributes;

    #[ORM\OneToOne(mappedBy: 'document', targetEntity: AssetHasFile::class)]
    private AssetHasFile $asset;

    public function __construct()
    {
        $this->setAttributes(new DocumentAttributes());
        parent::__construct();
    }

    public function getAttributes(): DocumentAttributes
    {
        return $this->attributes;
    }

    public function setAttributes(DocumentAttributes $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getAsset(): AssetHasFile
    {
        return $this->asset;
    }

    public function setAsset(AssetHasFile $asset): static
    {
        $this->asset = $asset;

        return $this;
    }

    public function getAssetType(): AssetType
    {
        return AssetType::Document;
    }
}
