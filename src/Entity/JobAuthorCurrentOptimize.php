<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CoreDamBundle\Repository\JobAuthorCurrentOptimizeRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobAuthorCurrentOptimizeRepository::class)]
class JobAuthorCurrentOptimize extends Job
{
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $processAll = false;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    #[Serialize]
    #[Assert\When(
        expression: 'false === this.isProcessAll()',
        constraints: [
            new Assert\NotNull(),
        ]
    )]
    private ?string $authorId = null;

    public function isProcessAll(): bool
    {
        return $this->processAll;
    }

    public function setProcessAll(bool $processAll): self
    {
        $this->processAll = $processAll;

        return $this;
    }

    public function getAuthorId(): ?string
    {
        return $this->authorId;
    }

    public function setAuthorId(?string $authorId): self
    {
        $this->authorId = $authorId;

        return $this;
    }
}
