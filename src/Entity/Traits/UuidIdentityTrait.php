<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Traits;

use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\NamedResourceTrait;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

trait UuidIdentityTrait
{
    use NamedResourceTrait;

    #[Serialize]
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::GUID, length: 36, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    protected ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function is(BaseIdentifiableInterface $identifiable): bool
    {
        if ($identifiable::getSystem() === static::getSystem()) {
            return $identifiable->getId() === $this->getId();
        }

        return false;
    }

    public function isNot(BaseIdentifiableInterface $identifiable): bool
    {
        return false === $this->is($identifiable);
    }
}
