<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AudioAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AudioPublicLink;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AudioFileRepository::class)]
class AudioFile extends AssetFile
{
    #[ORM\Embedded(class: AudioAttributes::class)]
    private AudioAttributes $attributes;

    #[ORM\Embedded(class: AudioPublicLink::class)]
    private AudioPublicLink $audioPublicLink;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private Asset $asset;

    #[ORM\OneToMany(mappedBy: 'audio', targetEntity: AssetSlot::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    private Collection $slots;

    public function __construct()
    {
        $this->setAttributes(new AudioAttributes());
        $this->setSlots(new ArrayCollection());
        $this->setAudioPublicLink(new AudioPublicLink());
        parent::__construct();
    }

    public function getAudioPublicLink(): AudioPublicLink
    {
        return $this->audioPublicLink;
    }

    public function setAudioPublicLink(AudioPublicLink $audioPublicLink): self
    {
        $this->audioPublicLink = $audioPublicLink;

        return $this;
    }

    public function getAttributes(): AudioAttributes
    {
        return $this->attributes;
    }

    public function setAttributes(AudioAttributes $attributes): self
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
        return AssetType::Audio;
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
