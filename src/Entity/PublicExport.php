<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Model\Enum\ExportType;
use AnzuSystems\CoreDamBundle\Repository\PublicExportRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PublicExportRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_slug', fields: ['slug'])]
class PublicExport implements TimeTrackingInterface, UserTrackingInterface, IdentifiableInterface
{
    use IdentityIntTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[Serialize]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private string $slug;

    #[ORM\ManyToOne(targetEntity: ExtSystem::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ExtSystem $extSystem;

    /**
     * @deprecated use {@see self::$licences} — kept (synced to the primary licence) for back-compat.
     */
    #[ORM\ManyToOne(targetEntity: AssetLicence::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    #[Serialize(handler: EntityIdHandler::class)]
    private ?AssetLicence $assetLicence = null;

    /**
     * Licences whose content this export serves.
     *
     * @var Collection<int, AssetLicence>
     */
    #[ORM\ManyToMany(targetEntity: AssetLicence::class, fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[ORM\JoinTable(name: 'public_export_asset_licence')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Serialize(handler: EntityIdHandler::class, type: AssetLicence::class)]
    #[Assert\Count(min: 1, minMessage: ValidationException::ERROR_FIELD_EMPTY)]
    private Collection $licences;

    #[ORM\Column(enumType: ExportType::class)]
    #[Serialize]
    private ExportType $type;

    public function __construct()
    {
        $this->setSlug('');
        $this->setExtSystem(new ExtSystem());
        $this->setType(ExportType::Default);
        $this->licences = new ArrayCollection();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

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
     * @deprecated use {@see self::getLicences()}
     */
    public function getAssetLicence(): ?AssetLicence
    {
        return $this->assetLicence;
    }

    public function setAssetLicence(?AssetLicence $assetLicence): self
    {
        $this->assetLicence = $assetLicence;

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

    public function addLicence(AssetLicence $licence): self
    {
        if (false === $this->licences->contains($licence)) {
            $this->licences->add($licence);
        }

        return $this;
    }

    public function removeLicence(AssetLicence $licence): self
    {
        $this->licences->removeElement($licence);

        return $this;
    }

    public function hasLicence(AssetLicence $licence): bool
    {
        return $this->licences->containsKey((int) $licence->getId());
    }

    public function getType(): ExportType
    {
        return $this->type;
    }

    public function setType(ExportType $type): self
    {
        $this->type = $type;

        return $this;
    }
}
