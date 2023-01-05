<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoAttributes;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoFileRepository::class)]
class VideoFile extends AssetFile
{
    #[ORM\Embedded(class: VideoAttributes::class)]
    private VideoAttributes $attributes;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    private ?Asset $previewImage;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private Asset $asset;

    #[ORM\OneToMany(mappedBy: 'video', targetEntity: AssetSlot::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    private Collection $slots;

    public function __construct()
    {
        $this->setAttributes(new VideoAttributes());
        $this->setSlots(new ArrayCollection());
        $this->setPreviewImage(null);
        parent::__construct();
    }

    public function getPreviewImage(): ?Asset
    {
        return $this->previewImage;
    }

    public function setPreviewImage(?Asset $previewImage): self
    {
        $this->previewImage = $previewImage;

        return $this;
    }

    public function getAttributes(): VideoAttributes
    {
        return $this->attributes;
    }

    public function setAttributes(VideoAttributes $attributes): self
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
        return AssetType::Video;
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
