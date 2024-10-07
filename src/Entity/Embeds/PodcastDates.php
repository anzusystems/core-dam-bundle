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
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    private ?DateTimeImmutable $importFrom;

    public function __construct()
    {
        $this->setImportFrom(null);
    }

    /**
     * @deprecated
     */
    public function getImportFrom(): ?DateTimeImmutable
    {
        return $this->importFrom;
    }

    /**
     * @deprecated
     */
    public function setImportFrom(?DateTimeImmutable $importFrom): self
    {
        $this->importFrom = $importFrom;

        return $this;
    }
}
