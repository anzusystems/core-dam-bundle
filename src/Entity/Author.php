<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AuthorFlags;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorType;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\UniqueConstraint(fields: ['name', 'identifier', 'extSystem'])]
#[BaseAppAssert\UniqueEntity(fields: ['name', 'identifier', 'extSystem'], errorAtPath: ['identifier'])]
class Author implements UuidIdentifiableInterface, UserTrackingInterface, TimeTrackingInterface, ExtSystemIndexableInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    private string $identifier;

    #[ORM\ManyToOne]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ExtSystem $extSystem;

    #[ORM\Embedded]
    #[Serialize]
    private AuthorFlags $flags;

    #[ORM\Column(enumType: AuthorType::class)]
    #[Serialize]
    private AuthorType $type;

    public function __construct()
    {
        $this->setName('');
        $this->setExtSystem(new ExtSystem());
        $this->setIdentifier('');
        $this->setFlags(new AuthorFlags());
        $this->setType(AuthorType::Default);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->extSystem;
    }

    public function setExtSystem(ExtSystem $extSystem): self
    {
        $this->extSystem = $extSystem;

        return $this;
    }

    public function getFlags(): AuthorFlags
    {
        return $this->flags;
    }

    public function setFlags(AuthorFlags $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function getType(): AuthorType
    {
        return $this->type;
    }

    public function setType(AuthorType $type): self
    {
        $this->type = $type;

        return $this;
    }
}
