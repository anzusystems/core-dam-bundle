<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoAttributes;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoFileRepository::class)]
class VideoFile extends AssetFile
{
    #[ORM\Embedded(class: VideoAttributes::class)]
    private VideoAttributes $attributes;

    #[ORM\OneToOne(mappedBy: 'video', targetEntity: AssetHasFile::class)]
    private AssetHasFile $asset;

    public function __construct()
    {
        $this->setAttributes(new VideoAttributes());
        parent::__construct();
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
        return AssetType::Video;
    }
}
