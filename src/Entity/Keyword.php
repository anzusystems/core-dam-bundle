<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Embeds\KeywordFlags;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\KeywordRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: KeywordRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_name_extSystem', fields: ['name', 'extSystem'])]
#[AppAssert\UniqueEntity(fields: ['name', 'extSystem'], errorAtPath: ['name'])]
class Keyword implements UuidIdentifiableInterface, UserTrackingInterface, TimeTrackingInterface, ExtSystemIndexableInterface
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

    #[ORM\ManyToOne]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ExtSystem $extSystem;

    #[ORM\Embedded]
    #[Serialize]
    private KeywordFlags $flags;

    public function __construct()
    {
        $this->setName('');
        $this->setExtSystem(new ExtSystem());
        $this->setFlags(new KeywordFlags());
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

    public function getExtSystem(): ExtSystem
    {
        return $this->extSystem;
    }

    public function setExtSystem(ExtSystem $extSystem): self
    {
        $this->extSystem = $extSystem;

        return $this;
    }

    public function getFlags(): KeywordFlags
    {
        return $this->flags;
    }

    public function setFlags(KeywordFlags $flags): self
    {
        $this->flags = $flags;

        return $this;
    }
}
