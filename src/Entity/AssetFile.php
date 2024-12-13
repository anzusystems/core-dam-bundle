<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\CopyableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileFlags;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetFileInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\FileSystemStorableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\NotifiableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\NotifyToTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @psalm-method DamUser getCreatedBy()
 * @psalm-method DamUser getModifiedBy()
 */
#[ORM\Entity(repositoryClass: AssetFileRepository::class)]
#[ORM\Index(name: 'IDX_licence_attributes_external_provider', fields: ['licence', 'assetAttributes.originExternalProvider'])]
#[ORM\Index(name: 'IDX_attributes_status', fields: ['assetAttributes.status'])]
#[ORM\Index(name: 'IDX_licence_attributes_status_checksum', fields: ['licence', 'assetAttributes.status', 'assetAttributes.checksum'])]
#[ORM\InheritanceType(value: 'JOINED')]
abstract class AssetFile implements
    TimeTrackingInterface,
    UuidIdentifiableInterface,
    AssetFileInterface,
    UserTrackingInterface,
    FileSystemStorableInterface,
    NotifiableInterface,
    AssetLicenceInterface,
    CopyableInterface
{
    use TimeTrackingTrait;
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use NotifyToTrait;

    #[ORM\OneToMany(targetEntity: Chunk::class, mappedBy: 'assetFile', fetch: App::DOCTRINE_EXTRA_LAZY)]
    #[ORM\OrderBy(value: ['offset' => App::ORDER_ASC])]
    protected Collection $chunks;

    #[ORM\OneToOne(targetEntity: AssetFileMetadata::class)]
    protected AssetFileMetadata $metadata;

    #[ORM\OneToMany(targetEntity: AssetFileRoute::class, mappedBy: 'targetAssetFile', fetch: App::DOCTRINE_EXTRA_LAZY)]
    protected Collection $routes;

    #[ORM\OneToOne(targetEntity: AssetFileRoute::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    protected ?AssetFileRoute $mainRoute;

    #[ORM\Embedded(class: AssetFileAttributes::class)]
    protected AssetFileAttributes $assetAttributes;

    #[ORM\Embedded(class: AssetFileFlags::class)]
    protected AssetFileFlags $flags;

    #[ORM\ManyToOne(targetEntity: AssetLicence::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    protected AssetLicence $licence;

    public function __construct()
    {
        $this->setAssetAttributes(new AssetFileAttributes());
        $this->setCreatedAt(App::getAppDate());
        $this->setModifiedAt(App::getAppDate());
        $this->setChunks(new ArrayCollection());
        $this->setFlags(new AssetFileFlags());
        $this->setRoutes(new ArrayCollection());
        $this->setMainRoute(null);
    }

    public function __toString(): string
    {
        return (string) $this->getId();
    }

    /**
     * @return Collection<int, AssetFileRoute>
     */
    public function getRoutes(): Collection
    {
        return $this->routes;
    }

    public function setRoutes(Collection $routes): void
    {
        $this->routes = $routes;
    }

    public function getMetadata(): AssetFileMetadata
    {
        return $this->metadata;
    }

    public function setMetadata(AssetFileMetadata $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAssetAttributes(): AssetFileAttributes
    {
        return $this->assetAttributes;
    }

    public function setAssetAttributes(AssetFileAttributes $assetAttributes): static
    {
        $this->assetAttributes = $assetAttributes;

        return $this;
    }

    public function getMainRoute(): ?AssetFileRoute
    {
        return $this->mainRoute;
    }

    public function setMainRoute(?AssetFileRoute $mainRoute): self
    {
        $this->mainRoute = $mainRoute;

        return $this;
    }

    /**
     * @return Collection<int, Chunk>
     */
    public function getChunks(): Collection
    {
        return $this->chunks;
    }

    public function setChunks(Collection $chunks): static
    {
        $this->chunks = $chunks;

        return $this;
    }

    public function getFlags(): AssetFileFlags
    {
        return $this->flags;
    }

    public function setFlags(AssetFileFlags $flags): static
    {
        $this->flags = $flags;

        return $this;
    }

    public function getLicence(): AssetLicence
    {
        return $this->licence;
    }

    public function setLicence(AssetLicence $licence): self
    {
        $this->licence = $licence;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->getAssetAttributes()->getFilePath();
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->getLicence()->getExtSystem();
    }

    protected function copyBase(self $assetFile): static
    {
        $this->setAssetAttributes(new AssetFileAttributes());
        $this->setCreatedAt(App::getAppDate());
        $this->setModifiedAt(App::getAppDate());
        $this->setChunks(new ArrayCollection());
        $this->setFlags(new AssetFileFlags());
        $this->setRoutes(new ArrayCollection());
        $this->setMainRoute(null);

        return $assetFile
            ->setAssetAttributes(clone $this->getAssetAttributes())
            ->setFlags(clone $this->getFlags())
        ;
    }
}
