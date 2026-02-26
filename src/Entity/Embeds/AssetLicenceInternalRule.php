<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetLicenceInternalRule
{
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $active = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    private ?DateTimeImmutable $markAsInternalSince = null;

    public function __construct()
    {
        $this->setActive(false);
        $this->setMarkAsInternalSince(null);
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

    public function getMarkAsInternalSince(): ?DateTimeImmutable
    {
        return $this->markAsInternalSince;
    }

    public function setMarkAsInternalSince(?DateTimeImmutable $markAsInternalSince): self
    {
        $this->markAsInternalSince = $markAsInternalSince;

        return $this;
    }
}
