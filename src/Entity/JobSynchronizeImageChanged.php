<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CoreDamBundle\Repository\JobSynchronizeImageChangedRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobSynchronizeImageChangedRepository::class)]
class JobSynchronizeImageChanged extends Job
{
    #[ORM\Column(type: Types::INTEGER)]
    #[Serialize]
    #[Assert\NotNull]
    #[Assert\Positive]
    private int $targetLicenceId = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    private ?DateTimeImmutable $processFrom = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Serialize]
    #[Assert\Range(min: 10, max: 1000)]
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
