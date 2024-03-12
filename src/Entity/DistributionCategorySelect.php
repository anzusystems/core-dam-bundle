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
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\DistributionCategorySelectRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DistributionCategorySelectRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_extSystem_serviceSlug_type', fields: ['extSystem', 'serviceSlug', 'type'])]
#[BaseAppAssert\UniqueEntity(fields: ['extSystem', 'serviceSlug', 'type'])]
class DistributionCategorySelect implements TimeTrackingInterface, UserTrackingInterface, UuidIdentifiableInterface, ExtSystemInterface
{
    use UuidIdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;

    #[Serialize]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private string $serviceSlug;

    #[Serialize]
    #[ORM\Column(enumType: AssetType::class)]
    private AssetType $type;

    #[Serialize(handler: EntityIdHandler::class)]
    #[ORM\ManyToOne]
    #[BaseAppAssert\NotEmptyId]
    private ExtSystem $extSystem;

    #[Serialize(type: DistributionCategoryOption::class)]
    #[ORM\OneToMany(mappedBy: 'select', targetEntity: DistributionCategoryOption::class)]
    #[ORM\OrderBy(['position' => App::ORDER_ASC])]
    #[Assert\Count(min: 1, minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN)]
    #[Assert\Valid]
    private Collection $options;

    public function __construct()
    {
        $this->setServiceSlug('');
        $this->setType(AssetType::Default);
        $this->setExtSystem(new ExtSystem());
        $this->setOptions(new ArrayCollection());
    }

    public function getServiceSlug(): string
    {
        return $this->serviceSlug;
    }

    public function setServiceSlug(string $serviceSlug): self
    {
        $this->serviceSlug = $serviceSlug;

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

    /**
     * @return Collection<int, DistributionCategoryOption>
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    /**
     * @param Collection<int, DistributionCategoryOption> $options
     */
    public function setOptions(Collection $options): self
    {
        $this->options = $options;

        return $this;
    }
}
