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
use AnzuSystems\CoreDamBundle\Entity\Interfaces\PositionableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\PositionTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\DistributionCategoryOptionRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DistributionCategoryOptionRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_select_name_value', fields: ['select', 'name', 'value'])]
#[BaseAppAssert\UniqueEntity(fields: ['select', 'name', 'value'])]
class DistributionCategoryOption implements TimeTrackingInterface, UserTrackingInterface, UuidIdentifiableInterface, PositionableInterface
{
    use UuidIdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;
    use PositionTrait;

    #[Serialize]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private string $name;

    #[Serialize]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private string $value;

    #[Serialize]
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $assignable;

    #[Serialize(handler: EntityIdHandler::class)]
    #[ORM\ManyToOne(targetEntity: DistributionCategorySelect::class, inversedBy: 'options')]
    #[BaseAppAssert\NotEmptyId]
    private DistributionCategorySelect $select;

    public function __construct()
    {
        $this->setId(null);
        $this->setName('');
        $this->setValue('');
        $this->setAssignable(true);
        $this->setSelect(new DistributionCategorySelect());
    }

    #[Serialize]
    public function getServiceSlug(): string
    {
        return $this->getSelect()->getServiceSlug();
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

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function isAssignable(): bool
    {
        return $this->assignable;
    }

    public function setAssignable(bool $assignable): self
    {
        $this->assignable = $assignable;

        return $this;
    }

    public function getSelect(): DistributionCategorySelect
    {
        return $this->select;
    }

    public function setSelect(DistributionCategorySelect $select): self
    {
        $this->select = $select;

        return $this;
    }
}
