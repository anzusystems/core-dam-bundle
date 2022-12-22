<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\DocumentAttributes;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\DocumentFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentFileRepository::class)]
class DocumentFile extends AssetFile
{
    #[ORM\Embedded(class: DocumentAttributes::class)]
    private DocumentAttributes $attributes;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private Asset $asset;

    #[ORM\OneToMany(mappedBy: 'document', targetEntity: AssetSlot::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    private Collection $slots;

    public function __construct()
    {
        $this->setAttributes(new DocumentAttributes());
        $this->setSlots(new ArrayCollection());
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

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): static
    {
        $this->asset = $asset;

        return $this;
    }

    public function getAssetType(): AssetType
    {
        return AssetType::Document;
    }

    public function getSlots(): Collection
    {
        return $this->slots;
    }

    public function setSlots(Collection $slots): self
    {
        $this->slots = $slots;

        return $this;
    }
}
