<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints\UniqueEntity;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use AnzuSystems\SerializerBundle\Metadata\ContainerParam;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExtSystemRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_slug', columns: ['slug'])]
#[UniqueEntity(fields: ['slug'])]
#[ORM\Cache(usage: App::CACHE_STRATEGY)]
class ExtSystem implements IdentifiableInterface, UserTrackingInterface, TimeTrackingInterface, ExtSystemInterface
{
    use IdentityIntTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[Serialize]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private string $name;

    /**
     * Slug is used for specify asset upload path
     */
    #[Serialize]
    #[ORM\Column(type: Types::STRING, length: 32)]
    #[Assert\Length(
        min: 3,
        max: 32,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private string $slug;

    #[ORM\OneToMany(mappedBy: 'extSystem', targetEntity: AssetLicence::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    private Collection $licences;

    #[ORM\ManyToMany(targetEntity: DamUser::class, mappedBy: 'adminToExtSystems', fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(DamUser::class))]
    private Collection $adminUsers;

    public function __construct()
    {
        $this->setName('');
        $this->setSlug('');
        $this->setLicences(new ArrayCollection());
        $this->setAdminUsers(new ArrayCollection());
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

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @return Collection<int, AssetLicence>
     */
    public function getLicences(): Collection
    {
        return $this->licences;
    }

    /**
     * @template TKey of array-key
     *
     * @param Collection<TKey, AssetLicence> $licences
     */
    public function setLicences(Collection $licences): self
    {
        $this->licences = $licences;

        return $this;
    }

    /**
     * @return Collection<int, DamUser>
     */
    public function getAdminUsers(): Collection
    {
        return $this->adminUsers;
    }

    /**
     * @template TKey of array-key
     *
     * @param Collection<TKey, DamUser> $adminUsers
     */
    public function setAdminUsers(Collection $adminUsers): self
    {
        $this->adminUsers = $adminUsers;

        return $this;
    }

    public function getExtSystem(): self
    {
        return $this;
    }
}
