<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CoreDamBundle\Repository\JobAssetFileReprocessInternalFlagRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobAssetFileReprocessInternalFlagRepository::class)]
class JobAssetFileReprocessInternalFlag extends Job
{
    #[ORM\Column(type: Types::INTEGER)]
    #[Serialize]
    #[Assert\NotNull]
    #[Assert\Positive]
    private int $targetLicenceId = 0;

    public function getTargetLicenceId(): int
    {
        return $this->targetLicenceId;
    }

    public function setTargetLicenceId(int $targetLicenceId): self
    {
        $this->targetLicenceId = $targetLicenceId;

        return $this;
    }
}
