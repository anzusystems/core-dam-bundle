<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityTrait;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Repository\AssetListViewRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssetListViewRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_name_ext_system', fields: ['name', 'extSystem'])]
#[BaseAppAssert\UniqueEntity(fields: ['extSystem', 'name'], errorAtPath: ['name'])]
#[AppAssert\AssetListView]
class AssetListView implements
    IdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface
{
    use IdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;

    private const int POSITION_MIN = -32_768;
    private const int POSITION_MAX = 32_767;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    #[Serialize]
    private string $name;

    #[ORM\ManyToOne(targetEntity: ExtSystem::class)]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ExtSystem $extSystem;

    /**
     * Lower position wins when more views apply to the same user.
     */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => App::ZERO])]
    #[Assert\Range(
        min: self::POSITION_MIN,
        max: self::POSITION_MAX,
        notInRangeMessage: ValidationException::ERROR_FIELD_INVALID
    )]
    #[Serialize]
    private int $position;

    /**
     * Users of these licence groups get the view. Empty collection targets whole external system.
     */
    #[ORM\ManyToMany(targetEntity: AssetLicenceGroup::class, fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[ORM\JoinTable(name: 'licence_group_in_list_view')]
    #[Serialize(handler: EntityIdHandler::class, type: AssetLicenceGroup::class)]
    private Collection $groups;

    #[ORM\ManyToMany(targetEntity: AssetLicence::class, fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[ORM\JoinTable(name: 'asset_licence_in_list_view')]
    #[Serialize(handler: EntityIdHandler::class, type: AssetLicence::class)]
    #[Assert\Count(
        min: 1,
        max: AssetLicence::COLLECTION_MAX,
        minMessage: ValidationException::ERROR_FIELD_RANGE_MIN,
        maxMessage: ValidationException::ERROR_FIELD_RANGE_MAX
    )]
    private Collection $licences;

    #[ORM\ManyToOne(targetEntity: AssetLicence::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Serialize(handler: EntityIdHandler::class)]
    private ?AssetLicence $uploadLicence;

    public function __construct()
    {
        $this->setName(App::EMPTY_STRING);
        $this->setExtSystem(new ExtSystem());
        $this->setPosition(App::ZERO);
        $this->setGroups(new ArrayCollection());
        $this->setLicences(new ArrayCollection());
        $this->setUploadLicence(null);
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return Collection<int, AssetLicenceGroup>
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    /**
     * @param Collection<int, AssetLicenceGroup> $groups
     */
    public function setGroups(Collection $groups): self
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * @return Collection<int, AssetLicence>
     */
    public function getLicences(): Collection
    {
        return $this->licences;
    }

    /**
     * @param Collection<int, AssetLicence> $licences
     */
    public function setLicences(Collection $licences): self
    {
        $this->licences = $licences;

        return $this;
    }

    public function getUploadLicence(): ?AssetLicence
    {
        return $this->uploadLicence;
    }

    public function setUploadLicence(?AssetLicence $uploadLicence): self
    {
        $this->uploadLicence = $uploadLicence;

        return $this;
    }
}
