<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CoreDamBundle\Repository\JobAuthorNameReindexRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobAuthorNameReindexRepository::class)]
class JobAuthorNameReindex extends Job
{
    #[ORM\Column(type: Types::STRING, length: 36)]
    #[Serialize]
    #[Assert\NotBlank]
    private string $authorId = '';

    public function getAuthorId(): string
    {
        return $this->authorId;
    }

    public function setAuthorId(string $authorId): self
    {
        $this->authorId = $authorId;

        return $this;
    }
}
