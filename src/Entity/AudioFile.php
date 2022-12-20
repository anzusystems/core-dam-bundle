<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\Entity\Embeds\AudioAttributes;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AudioFileRepository::class)]
class AudioFile extends AssetFile
{
    #[ORM\Embedded(class: AudioAttributes::class)]
    private AudioAttributes $attributes;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    private Asset $asset;

    public function __construct()
    {
        $this->setAttributes(new AudioAttributes());
        parent::__construct();
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
}
