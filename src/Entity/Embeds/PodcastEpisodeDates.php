<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class PodcastEpisodeDates
{
    // todo remove nullable after migration.
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    private ?DateTimeImmutable $publicationDate;

    public function __construct()
    {
        $this->setPublicationDate(App::getAppDate());
    }

    public function getPublicationDate(): ?DateTimeImmutable
    {
        return $this->publicationDate;
    }

    public function setPublicationDate(?DateTimeImmutable $publicationDate): self
    {
        $this->publicationDate = $publicationDate;

        return $this;
    }
}
