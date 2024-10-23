<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\CopyableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\FileSystemStorableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\ImageFileOptimalResizeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageFileOptimalResizeRepository::class)]
class ImageFileOptimalResize implements UuidIdentifiableInterface, FileSystemStorableInterface, CopyableInterface
{
    use UuidIdentityTrait;

    #[ORM\Column(type: Types::INTEGER)]
    private int $requestedSize;

    #[ORM\Column(type: Types::INTEGER)]
    private int $width;

    #[ORM\Column(type: Types::INTEGER)]
    private int $height;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $filePath;

    #[ORM\ManyToOne(targetEntity: ImageFile::class, inversedBy: 'resizes')]
    private ImageFile $image;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $original;

    public function __construct()
    {
        $this->setWidth(0);
        $this->setHeight(0);
        $this->setFilePath('');
        $this->setRequestedSize(0);
        $this->setOriginal(false);
    }

    public function __copy(): self
    {
        return (new self())
            ->setWidth($this->getWidth())
            ->setHeight($this->getHeight())
            ->setRequestedSize($this->getRequestedSize())
            ->setOriginal($this->isOriginal())
        ;
    }

    public function getRequestedSize(): int
    {
        return $this->requestedSize;
    }

    public function setRequestedSize(int $requestedSize): self
    {
        $this->requestedSize = $requestedSize;

        return $this;
    }

    public function getImage(): ImageFile
    {
        return $this->image;
    }

    public function setImage(ImageFile $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
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

    public function getAssetType(): AssetType
    {
        return AssetType::Image;
    }

    public function isOriginal(): bool
    {
        return $this->original;
    }

    public function setOriginal(bool $original): self
    {
        $this->original = $original;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->getImage()->getExtSystem();
    }
}
