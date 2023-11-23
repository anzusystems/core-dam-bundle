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
#[ORM\UniqueConstraint(name: 'UNIQ_asset_file_id', fields: ['assetFileId'])]
class AssetFileRoute implements UuidIdentifiableInterface, TimeTrackingInterface, UserTrackingInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $path;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $slug;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $assetFileId;

    public function __construct()
    {
        $this->setPath('');
        $this->setSlug('');
        $this->setActive(true);
        $this->setAssetFileId('');
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getAssetFileId(): string
    {
        return $this->assetFileId;
    }

    public function setAssetFileId(string $assetFileId): self
    {
        $this->assetFileId = $assetFileId;
        return $this;
    }
}
