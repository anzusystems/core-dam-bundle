<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CoreDamBundle\Repository\JobAuthorCurrentOptimizeRepository;
use AnzuSystems\CoreDamBundle\Repository\JobImageCopyRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobAuthorCurrentOptimizeRepository::class)]
class JobAuthorCurrentOptimize extends Job
{
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $processAll = false;

    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $authorIds = [];

    public function isProcessAll(): bool
    {
        return $this->processAll;
    }

    public function setProcessAll(bool $processAll): self
    {
        $this->processAll = $processAll;
        return $this;
    }

    public function getAuthorIds(): array
    {
        return $this->authorIds;
    }

    public function setAuthorIds(array $authorIds): self
    {
        $this->authorIds = $authorIds;
        return $this;
    }
}
