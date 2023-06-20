<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\AssetFileMetadataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @psalm-method DamUser getCreatedBy()
 * @psalm-method DamUser getModifiedBy()
 */
#[ORM\Entity(repositoryClass: AssetFileMetadataRepository::class)]
class AssetFileMetadata implements TimeTrackingInterface, UuidIdentifiableInterface, UserTrackingInterface
{
    use TimeTrackingTrait;
    use UuidIdentityTrait;
    use UserTrackingTrait;

    #[ORM\Column(type: Types::JSON)]
    private array $exifData;

    public function __construct()
    {
        $this->setExifData([]);
    }

    public function getExifData(): array
    {
        return $this->exifData;
    }

    public function setExifData(array $exifData): self
    {
        $this->exifData = $exifData;

        return $this;
    }
}
