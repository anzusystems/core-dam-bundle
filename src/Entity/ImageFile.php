<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\ImageAttributes;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageFileRepository::class)]
class ImageFile extends AssetFile
{
    #[ORM\Embedded(class: ImageAttributes::class)]
    private ImageAttributes $imageAttributes;

    #[ORM\OneToMany(mappedBy: 'image', targetEntity: ImageFileOptimalResize::class)]
    #[ORM\OrderBy(value: ['requestedSize' => Criteria::ASC])]
    private Collection $resizes;

    #[ORM\OneToMany(mappedBy: 'image', targetEntity: RegionOfInterest::class)]
    private Collection $regionsOfInterest;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private Asset $asset;

    #[ORM\OneToMany(mappedBy: 'image', targetEntity: AssetSlot::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    private Collection $slots;

    public function __construct()
    {
        $this->setImageAttributes(new ImageAttributes());
        $this->setRegionsOfInterest(new ArrayCollection());
        $this->setResizes(new ArrayCollection());
        $this->setSlots(new ArrayCollection());
        parent::__construct();
    }

    /**
     * @return Collection<int, ImageFileOptimalResize>
     */
    public function getResizes(): Collection
    {
        return $this->resizes;
    }

    public function setResizes(Collection $resizes): self
    {
        $this->resizes = $resizes;

        return $this;
    }

    public function getImageAttributes(): ImageAttributes
    {
        return $this->imageAttributes;
    }

    public function setImageAttributes(ImageAttributes $imageAttributes): self
    {
        $this->imageAttributes = $imageAttributes;

        return $this;
    }

    /**
     * @return Collection<int, RegionOfInterest>
     */
    public function getRegionsOfInterest(): Collection
    {
        return $this->regionsOfInterest;
    }

    public function setRegionsOfInterest(Collection $regionsOfInterest): self
    {
        $this->regionsOfInterest = $regionsOfInterest;

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
        return AssetType::Image;
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
