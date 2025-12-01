<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\ImageAttributes;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageFileRepository::class)]
class ImageFile extends AssetFile
{
    #[ORM\Embedded(class: ImageAttributes::class)]
    private ImageAttributes $imageAttributes;

    #[ORM\OneToMany(targetEntity: ImageFileOptimalResize::class, mappedBy: 'image')]
    #[ORM\OrderBy(value: ['requestedSize' => App::ORDER_ASC])]
    private Collection $resizes;

    #[ORM\OneToMany(targetEntity: RegionOfInterest::class, mappedBy: 'image')]
    private Collection $regionsOfInterest;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private Asset $asset;

    #[ORM\OneToMany(targetEntity: AssetSlot::class, mappedBy: 'image', fetch: App::DOCTRINE_EXTRA_LAZY)]
    private Collection $slots;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private DateTimeImmutable $manipulatedAt;

    public function __construct()
    {
        $this->setImageAttributes(new ImageAttributes());
        $this->setRegionsOfInterest(new ArrayCollection());
        $this->setResizes(new ArrayCollection());
        $this->setSlots(new ArrayCollection());
        $this->setLicence(new AssetLicence());
        $this->setManipulatedAt(App::getAppDate());
        parent::__construct();
    }

    public function __copy(): self
    {
        $regionsOfInterest = $this->getRegionsOfInterest()->map(
            static fn (RegionOfInterest $regionOfInterest): RegionOfInterest => $regionOfInterest->__copy()
        );

        $assetFile = (new self())
            ->setImageAttributes(clone $this->getImageAttributes())
            ->setRegionsOfInterest($regionsOfInterest)
            ->setManipulatedAt($this->getManipulatedAt())
        ;

        return parent::copyBase($assetFile);
    }

    /**
     * @return Collection<int, ImageFileOptimalResize>
     */
    public function getResizes(): Collection
    {
        return $this->resizes;
    }

    /**
     * @param Collection<int, ImageFileOptimalResize> $resizes
     */
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

    /**
     * @param Collection<int, RegionOfInterest> $regionsOfInterest
     */
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

    public function addSlot(AssetSlot $slot): static
    {
        $this->slots->add($slot);
        $slot->setAssetFile($this);

        return $this;
    }

    public function getManipulatedAt(): DateTimeImmutable
    {
        return $this->manipulatedAt;
    }

    public function setManipulatedAt(DateTimeImmutable $manipulatedAt): self
    {
        $this->manipulatedAt = $manipulatedAt;

        return $this;
    }
}
