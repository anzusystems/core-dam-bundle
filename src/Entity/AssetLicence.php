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
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints\UniqueEntity;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssetLicenceRepository::class)]
#[ORM\UniqueConstraint(fields: ['name'])]
#[ORM\UniqueConstraint(fields: ['extSystem', 'extId'])]
#[ORM\Index(fields: ['name'])]
#[UniqueEntity(fields: ['extSystem', 'extId'], errorAtPath: ['extId'])]
class AssetLicence implements IdentifiableInterface, UserTrackingInterface, TimeTrackingInterface, AssetLicenceInterface, ExtSystemInterface
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
    #[ORM\ManyToOne(targetEntity: ExtSystem::class, inversedBy: 'licences')]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ExtSystem $extSystem;

    /**
     * External system licence ID (e.g. BlogId)
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private string $extId;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $limitedFiles;

    /**
     * List of users who belongs to licence.
     */
    #[ORM\ManyToMany(targetEntity: DamUser::class, mappedBy: 'assetLicences', fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    private Collection $users;

    public function __construct()
    {
        $this->setName('');
        $this->setExtSystem(new ExtSystem());
        $this->setExtId('');
        $this->setUsers(new ArrayCollection());
        $this->setLimitedFiles(false);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefaultName(): string
    {
        return $this->extSystem->getName() . ' - ' . $this->extId;
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

    public function getExtId(): string
    {
        return $this->extId;
    }

    public function setExtId(string $extId): self
    {
        $this->extId = $extId;

        return $this;
    }

    public function isLimitedFiles(): bool
    {
        return $this->limitedFiles;
    }

    public function isNotLimitedFiles(): bool
    {
        return false === $this->isLimitedFiles();
    }

    public function setLimitedFiles(bool $limitedFiles): self
    {
        $this->limitedFiles = $limitedFiles;

        return $this;
    }

    /**
     * @return Collection<int, DamUser>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function setUsers(Collection $users): self
    {
        $this->users = $users;

        return $this;
    }

    public function getLicence(): self
    {
        return $this;
    }
}
