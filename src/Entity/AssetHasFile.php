<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Repository\AssetHasFileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssetHasFileRepository::class)]
#[ORM\Index(fields: ['versionTitle'], name: 'IDX_version_title')]
#[ORM\Index(fields: ['default'], name: 'IDX_default')]
class AssetHasFile implements UuidIdentifiableInterface
{
    use UuidIdentityTrait;

    #[ORM\Column(type: Types::STRING)]
    private string $versionTitle;

    #[ORM\Column(name: 'is_default', type: Types::BOOLEAN)]
    private bool $default;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'files')]
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
        $this->setVersionTitle('');
        $this->setDefault(false);
        $this->setImage(null);
        $this->setAudio(null);
        $this->setVideo(null);
        $this->setDocument(null);
    }

    public function getVersionTitle(): string
    {
        return $this->versionTitle;
    }

    public function setVersionTitle(string $versionTitle): self
    {
        $this->versionTitle = $versionTitle;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): self
    {
        $this->default = $default;

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
