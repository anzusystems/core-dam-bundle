<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class DamUser extends AnzuUser
{
    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    protected array $allowedAssetExternalProviders = [];

    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    protected array $allowedDistributionServices = [];

    #[ORM\ManyToMany(targetEntity: AssetLicence::class, inversedBy: 'users', fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[ORM\JoinTable(name: 'user_asset_licence')]
    #[ORM\JoinColumn(name: 'user_id', onDelete: 'cascade')]
    #[Serialize(handler: EntityIdHandler::class, type: AssetLicence::class)]
    protected Collection $assetLicences;

    #[ORM\ManyToMany(targetEntity: ExtSystem::class, inversedBy: 'adminUsers', fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[ORM\JoinTable(name: 'admins_to_ext_systems')]
    #[ORM\JoinColumn(name: 'user_id', onDelete: 'cascade')]
    #[Serialize(handler: EntityIdHandler::class, type: ExtSystem::class)]
    protected Collection $adminToExtSystems;

    #[ORM\ManyToMany(targetEntity: AssetLicenceGroup::class, inversedBy: 'users', fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[ORM\JoinTable(name: 'user_in_licence_groups')]
    #[ORM\JoinColumn(name: 'user_id', onDelete: 'cascade')]
    #[Serialize(handler: EntityIdHandler::class, type: ExtSystem::class)]
    #[ORM\Cache(usage: App::CACHE_STRATEGY)]
    protected Collection $licenceGroups;

    #[ORM\ManyToMany(targetEntity: ExtSystem::class, fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[ORM\JoinTable(name: 'users_to_ext_systems')]
    #[ORM\JoinColumn(name: 'user_id', onDelete: 'cascade')]
    #[Serialize(handler: EntityIdHandler::class, type: ExtSystem::class)]
    protected Collection $userToExtSystems;

    public function getUserIdentifier(): string
    {
        return (string) ($this->getId() ?? 0);
    }

    /**
     * @return list<string>
     */
    public function getAllowedDistributionServices(): array
    {
        return $this->allowedDistributionServices;
    }

    public function setAllowedDistributionServices(array $allowedDistributionServices): static
    {
        $this->allowedDistributionServices = $allowedDistributionServices;

        return $this;
    }

    public function hasAllowedDistributionServices(string $distributionService): bool
    {
        return in_array($distributionService, $this->getAllowedDistributionServices(), true);
    }

    /**
     * @return list<string>
     */
    public function getAllowedAssetExternalProviders(): array
    {
        return $this->allowedAssetExternalProviders;
    }

    public function hasAllowedExternalProvider(string $providerName): bool
    {
        return in_array($providerName, $this->getAllowedAssetExternalProviders(), true);
    }

    public function setAllowedAssetExternalProviders(array $allowedAssetExternalProviders): static
    {
        $this->allowedAssetExternalProviders = $allowedAssetExternalProviders;

        return $this;
    }

    /**
     * @return Collection<int, AssetLicence>
     */
    public function getAssetLicences(): Collection
    {
        return $this->assetLicences;
    }

    /**
     * @template TKey of array-key
     *
     * @param Collection<TKey, AssetLicence> $assetLicences
     */
    public function setAssetLicences(Collection $assetLicences): static
    {
        $this->assetLicences = $assetLicences;

        return $this;
    }

    /**
     * @return Collection<int, ExtSystem>
     */
    public function getAdminToExtSystems(): Collection
    {
        return $this->adminToExtSystems;
    }

    /**
     * @template TKey of array-key
     *
     * @param Collection<TKey, ExtSystem> $adminToExtSystems
     */
    public function setAdminToExtSystems(Collection $adminToExtSystems): static
    {
        $this->adminToExtSystems = $adminToExtSystems;

        return $this;
    }

    /**
     * @return Collection<int, ExtSystem>
     */
    public function getUserToExtSystems(): Collection
    {
        return $this->userToExtSystems;
    }

    /**
     * @template TKey of array-key
     *
     * @param Collection<TKey, ExtSystem> $userToExtSystems
     */
    public function setUserToExtSystems(Collection $userToExtSystems): static
    {
        $this->userToExtSystems = $userToExtSystems;

        return $this;
    }

    /**
     * @return Collection<int, AssetLicenceGroup>
     */
    public function getLicenceGroups(): Collection
    {
        return $this->licenceGroups;
    }

    /**
     * @template TKey of array-key
     *
     * @param Collection<TKey, AssetLicenceGroup> $licenceGroups
     */
    public function setLicenceGroups(Collection $licenceGroups): static
    {
        $this->licenceGroups = $licenceGroups;

        return $this;
    }
}
