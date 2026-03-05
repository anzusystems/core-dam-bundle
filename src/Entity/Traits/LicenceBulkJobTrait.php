<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Traits;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait LicenceBulkJobTrait
{
    #[ORM\Column(type: Types::INTEGER)]
    #[Serialize]
    #[Assert\NotNull]
    #[Assert\Positive]
    private int $targetLicenceId = App::ZERO;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    private ?DateTimeImmutable $processFrom = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    #[Assert\When(
        expression: 'this.getProcessFrom() !== null and this.getProcessUntil() !== null',
        constraints: [
            new Assert\GreaterThan(propertyPath: 'processFrom'),
        ]
    )]
    private ?DateTimeImmutable $processUntil = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Serialize]
    #[Assert\Range(min: 10, max: 1_000)]
    private int $bulkSize = 500;

    public function getTargetLicenceId(): int
    {
        return $this->targetLicenceId;
    }

    public function setTargetLicenceId(int $targetLicenceId): self
    {
        $this->targetLicenceId = $targetLicenceId;

        return $this;
    }

    public function getProcessFrom(): ?DateTimeImmutable
    {
        return $this->processFrom;
    }

    public function setProcessFrom(?DateTimeImmutable $processFrom): self
    {
        $this->processFrom = $processFrom;

        return $this;
    }

    public function getProcessUntil(): ?DateTimeImmutable
    {
        return $this->processUntil;
    }

    public function setProcessUntil(?DateTimeImmutable $processUntil): self
    {
        $this->processUntil = $processUntil;

        return $this;
    }

    public function getBulkSize(): int
    {
        return $this->bulkSize;
    }

    public function setBulkSize(int $bulkSize): self
    {
        $this->bulkSize = $bulkSize;

        return $this;
    }
}
