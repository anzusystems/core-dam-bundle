<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Traits;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait PersonNameTrait
{
    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Serialize]
    protected string $firstName = '';

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Serialize]
    protected string $lastName = '';

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }
}
