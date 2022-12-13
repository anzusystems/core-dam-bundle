<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\FileSystemStorableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\ChunkRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChunkRepository::class)]
#[ORM\Index(fields: ['offset'], name: 'IDX_offset')]
class Chunk implements UuidIdentifiableInterface, FileSystemStorableInterface, AssetLicenceInterface
{
    use UuidIdentityTrait;

    #[ORM\ManyToOne(targetEntity: AssetFile::class, inversedBy: 'chunks')]
    private AssetFile $assetFile;

    #[Serialize]
    #[ORM\Column(type: Types::INTEGER)]
    private int $offset;

    #[Serialize]
    #[ORM\Column(type: Types::INTEGER)]
    private int $size;

    #[Serialize]
    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $mimeType;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $filePath;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $checksum;

    public function __construct()
    {
        $this->setOffset(0);
        $this->setSize(0);
        $this->setMimeType('');
        $this->setFilePath('');
        $this->setChecksum('');
    }

    public function getAssetFile(): AssetFile
    {
        return $this->assetFile;
    }

    public function setAssetFile(AssetFile $assetFile): self
    {
        $this->assetFile = $assetFile;

        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function setChecksum(string $checksum): self
    {
        $this->checksum = $checksum;

        return $this;
    }

    public function isFirstChunk(): bool
    {
        return App::ZERO === $this->getOffset();
    }

    public function getAssetType(): AssetType
    {
        return $this->assetFile->getAssetType();
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->assetFile->getExtSystem();
    }

    public function getLicence(): AssetLicence
    {
        return $this->assetFile->getLicence();
    }
}
