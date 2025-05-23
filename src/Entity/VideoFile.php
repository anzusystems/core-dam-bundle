<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoAttributes;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ImagePreviewableInterface;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoFileRepository::class)]
class VideoFile extends AssetFile implements ImagePreviewableInterface
{
    #[ORM\OneToOne(targetEntity: ImagePreview::class)]
    #[Serialize]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    protected ?ImagePreview $imagePreview;

    #[ORM\Embedded(class: VideoAttributes::class)]
    private VideoAttributes $attributes;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private Asset $asset;

    #[ORM\OneToMany(targetEntity: AssetSlot::class, mappedBy: 'video', fetch: App::DOCTRINE_EXTRA_LAZY)]
    private Collection $slots;

    public function __construct()
    {
        $this->setAttributes(new VideoAttributes());
        $this->setSlots(new ArrayCollection());
        $this->setImagePreview(null);
        parent::__construct();
    }

    public function __copy(): self
    {
        $assetFile = (new self())
            ->setAttributes(clone $this->getAttributes())
        ;

        return parent::copyBase($assetFile);
    }

    public function getImagePreview(): ?ImagePreview
    {
        return $this->imagePreview;
    }

    public function setImagePreview(?ImagePreview $imagePreview): self
    {
        $this->imagePreview = $imagePreview;

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

    public function addSlot(AssetSlot $slot): static
    {
        $this->slots->add($slot);
        $slot->setAssetFile($this);

        return $this;
    }
}
