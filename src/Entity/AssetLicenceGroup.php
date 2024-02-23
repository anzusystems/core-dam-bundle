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
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceGroupRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssetLicenceGroupRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_name_ext_system', fields: ['name', 'extSystem'])]
#[BaseAppAssert\UniqueEntity(fields: ['extSystem', 'name'], errorAtPath: ['name'])]
#[ORM\Cache(usage: App::CACHE_STRATEGY)]
#[AppAssert\AssetLicenceGroup]
class AssetLicenceGroup implements
    IdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface
{
    use IdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    #[Serialize]
    private string $name;

    /**
     * Asset belongs to external system (e.g. Blogs, CMS, ...)
     */
    #[ORM\ManyToOne(targetEntity: ExtSystem::class)]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    #[ORM\Cache(usage: App::CACHE_STRATEGY)]
    private ExtSystem $extSystem;

    /**
     * Grouped licences
     */
    #[ORM\ManyToMany(targetEntity: AssetLicence::class, mappedBy: 'groups', fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[Serialize(handler: EntityIdHandler::class, type: AssetLicence::class)]
    private Collection $licences;

    /**
     * List of users who belongs to licenceGroup.
     */
    #[ORM\ManyToMany(targetEntity: DamUser::class, mappedBy: 'licenceGroups', fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    private Collection $users;

    public function __construct()
    {
        $this->setName('');
        $this->setLicences(new ArrayCollection());
        $this->setExtSystem(new ExtSystem());
        $this->setUsers(new ArrayCollection());
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

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function setUsers(Collection $users): self
    {
        $this->users = $users;
        return $this;
    }
}
