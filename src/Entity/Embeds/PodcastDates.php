<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class PodcastDates
{
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Serialize]
    private ?DateTimeImmutable $importFrom;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Serialize]
    private ?DateTimeImmutable $importUntil;

    public function __construct()
    {
        $this->setImportFrom(null);
        $this->setImportUntil(null);
    }

    public function getImportFrom(): ?DateTimeImmutable
    {
        return $this->importFrom;
    }

    public function setImportFrom(?DateTimeImmutable $importFrom): self
    {
        $this->importFrom = $importFrom;

        return $this;
    }

    public function getImportUntil(): ?DateTimeImmutable
    {
        return $this->importUntil;
    }

    public function setImportUntil(?DateTimeImmutable $importUntil): self
    {
        $this->importUntil = $importUntil;

        return $this;
    }
}
