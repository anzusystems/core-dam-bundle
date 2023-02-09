<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints\UniqueEntity;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\DistributionCategoryRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DistributionCategoryRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_type_extSystem_name', fields: ['type', 'extSystem', 'name'])]
#[UniqueEntity(fields: ['type', 'extSystem', 'name'])]
class DistributionCategory implements TimeTrackingInterface, UserTrackingInterface, UuidIdentifiableInterface, ExtSystemInterface
{
    use UuidIdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;

    #[Serialize]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private string $name;

    #[Serialize]
    #[ORM\Column(enumType: AssetType::class)]
    private AssetType $type;

    #[Serialize(handler: EntityIdHandler::class)]
    #[ORM\ManyToOne]
    private ExtSystem $extSystem;

    #[Serialize(handler: EntityIdHandler::class, type: DistributionCategoryOption::class)]
    #[ORM\ManyToMany(targetEntity: DistributionCategoryOption::class)]
    #[ORM\JoinTable(name: 'distribution_category_has_selected_option')]
    private Collection $selectedOptions;

    public function __construct()
    {
        $this->setName('');
        $this->setType(AssetType::Default);
        $this->setSelectedOptions(new ArrayCollection());
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

    public function getType(): AssetType
    {
        return $this->type;
    }

    public function setType(AssetType $type): self
    {
        $this->type = $type;

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

    public function getSelectedOptions(): Collection
    {
        return $this->selectedOptions;
    }

    #[Serialize(type: DistributionCategoryOption::class)]
    public function getSelectedOptionsDetail(): Collection
    {
        return $this->selectedOptions;
    }

    public function setSelectedOptions(Collection $selectedOptions): self
    {
        $this->selectedOptions = $selectedOptions;

        return $this;
    }
}
