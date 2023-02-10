<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetSlotFlags;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Repository\AssetSlotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssetSlotRepository::class)]
#[ORM\Index(fields: ['name'], name: 'IDX_name')]
#[ORM\Index(fields: ['flags.default'], name: 'IDX_default')]
#[ORM\UniqueConstraint(name: 'UNIQ_asset_file_asset_name', fields: ['asset', 'name', 'image', 'audio', 'video', 'image'])]
class AssetSlot implements UuidIdentifiableInterface, TimeTrackingInterface, UserTrackingInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    #[ORM\Embedded(class: AssetSlotFlags::class)]
    private AssetSlotFlags $flags;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'slots')]
    private Asset $asset;

    #[ORM\ManyToOne(targetEntity: ImageFile::class, inversedBy: 'slots')]
    private ?ImageFile $image;

    #[ORM\ManyToOne(targetEntity: AudioFile::class, inversedBy: 'slots')]
    private ?AudioFile $audio;

    #[ORM\ManyToOne(targetEntity: VideoFile::class, inversedBy: 'slots')]
    private ?VideoFile $video;

    #[ORM\ManyToOne(targetEntity: DocumentFile::class, inversedBy: 'slots')]
    private ?DocumentFile $document;

    public function __construct()
    {
        $this->setName('');
        $this->setFlags(new AssetSlotFlags());
        $this->setImage(null);
        $this->setAudio(null);
        $this->setVideo(null);
        $this->setDocument(null);
    }

    public function getFlags(): AssetSlotFlags
    {
        return $this->flags;
    }

    public function setFlags(AssetSlotFlags $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getImage(): ?ImageFile
    {
        return $this->image;
    }

    public function setImage(?ImageFile $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getAudio(): ?AudioFile
    {
        return $this->audio;
    }

    public function setAudio(?AudioFile $audio): self
    {
        $this->audio = $audio;

        return $this;
    }

    public function getVideo(): ?VideoFile
    {
        return $this->video;
    }

    public function setVideo(?VideoFile $video): self
    {
        $this->video = $video;

        return $this;
    }

    public function getDocument(): ?DocumentFile
    {
        return $this->document;
    }

    public function setDocument(?DocumentFile $document): self
    {
        $this->document = $document;

        return $this;
    }

    public function setAssetFile(AssetFile $assetFile): self
    {
        if ($assetFile instanceof ImageFile) {
            $this->setImage($assetFile);
        }
        if ($assetFile instanceof AudioFile) {
            $this->setAudio($assetFile);
        }
        if ($assetFile instanceof VideoFile) {
            $this->setVideo($assetFile);
        }
        if ($assetFile instanceof DocumentFile) {
            $this->setDocument($assetFile);
        }

        return $this;
    }

    public function getAssetFile(): AssetFile
    {
        if ($this->image) {
            return $this->image;
        }
        if ($this->audio) {
            return $this->audio;
        }
        if ($this->video) {
            return $this->video;
        }
        if ($this->document) {
            return $this->document;
        }

        throw new RuntimeException(sprintf(
            'Entity (%s) with id (%s) has no (%s) associated',
            self::class,
            $this->getId(),
            AssetFile::class,
        ));
    }
}
