<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\NotifiableInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\NotifyToTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionFailReason;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DistributionRepository::class)]
#[AppAssert\Distribution]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\Index(fields: ['assetFileId'], name: 'IDX_asset_file_id')]
#[ORM\Index(fields: ['assetId'], name: 'IDX_asset_id')]
#[ORM\Index(fields: ['assetFileId', 'distributionService'], name: 'IDX_asset_file_id_distribution_service')]
#[ORM\Index(fields: ['status'], name: 'IDX_status')]
abstract class Distribution implements
    UuidIdentifiableInterface,
    UserTrackingInterface,
    TimeTrackingInterface,
    NotifiableInterface,
    ExtSystemIndexableInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;
    use NotifyToTrait;
    public const string INDEX_NAME = 'distribution';

    #[ORM\Column(type: Types::STRING, length: 36)]
    protected string $assetFileId;

    #[ORM\Column(type: Types::STRING, length: 36)]
    protected string $assetId;

    #[ORM\Column(type: Types::STRING, length: 512)]
    protected string $extId;

    #[ORM\Column(type: Types::STRING, length: 128)]
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    protected string $distributionService;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(name: 'distribution_asset_id', nullable: true, onDelete: 'CASCADE')]
    protected ?Asset $asset;

    #[ORM\ManyToOne(targetEntity: AssetFile::class)]
    #[ORM\JoinColumn(name: 'distribution_asset_file_id', nullable: true, onDelete: 'CASCADE')]
    protected ?AssetFile $assetFile;

    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'blockedBy')]
    protected Collection $blocks;

    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'blocks')]
    #[Serialize(handler: EntityIdHandler::class, type: self::class)]
    protected Collection $blockedBy;

    #[ORM\Column(enumType: DistributionProcessStatus::class)]
    #[Serialize]
    protected DistributionProcessStatus $status;

    #[ORM\Column(enumType: DistributionProcessStatus::class)]
    #[Serialize]
    protected DistributionFailReason $failReason;

    #[ORM\Column(type: Types::JSON)]
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    protected array $distributionData;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    protected ?DateTimeImmutable $publishAt;

    public function __construct()
    {
        $this->setAssetId('');
        $this->setAssetFileId('');
        $this->setDistributionService('');
        $this->setStatus(DistributionProcessStatus::Default);
        $this->setBlockedBy(new ArrayCollection());
        $this->setBlocks(new ArrayCollection());
        $this->setExtId('');
        $this->setDistributionData([]);
        $this->setFailReason(DistributionFailReason::None);
        $this->setPublishAt(null);
        $this->setAsset(null);
        $this->setAssetFile(null);
    }

    abstract public function getDiscriminator(): string;


    public static function getIndexName(): string
    {
        return self::INDEX_NAME;
    }

    public function getPublishAt(): ?DateTimeImmutable
    {
        return $this->publishAt;
    }

    public function setPublishAt(?DateTimeImmutable $publishAt): static
    {
        $this->publishAt = $publishAt;

        return $this;
    }

    /**
     * @return Collection<int, Distribution>
     */
    public function getBlocks(): Collection
    {
        return $this->blocks;
    }

    public function setBlocks(Collection $blocks): static
    {
        $this->blocks = $blocks;

        return $this;
    }

    /**
     * @return Collection<int, Distribution>
     */
    public function getBlockedBy(): Collection
    {
        return $this->blockedBy;
    }

    public function setBlockedBy(Collection $blockedBy): static
    {
        $this->blockedBy = $blockedBy;

        return $this;
    }

    public function addBlockedBy(self $distribution): static
    {
        $this->blockedBy->add($distribution);
        $distribution->blocks->add($this);

        return $this;
    }

    public function getStatus(): DistributionProcessStatus
    {
        return $this->status;
    }

    public function setStatus(DistributionProcessStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDistributionService(): string
    {
        return $this->distributionService;
    }

    public function setDistributionService(string $distributionService): static
    {
        $this->distributionService = $distributionService;

        return $this;
    }

    #[Serialize]
    public function getAssetFileId(): string
    {
        return $this->assetFileId;
    }

    public function setAssetFileId(string $assetFileId): static
    {
        $this->assetFileId = $assetFileId;

        return $this;
    }

    #[Serialize]
    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function setAssetId(string $assetId): static
    {
        $this->assetId = $assetId;

        return $this;
    }

    #[Serialize]
    public function getExtId(): string
    {
        return $this->extId;
    }

    public function setExtId(string $extId): static
    {
        $this->extId = $extId;

        return $this;
    }

    public function getDistributionData(): array
    {
        return $this->distributionData;
    }

    public function setDistributionData(array $distributionData): static
    {
        $this->distributionData = $distributionData;

        return $this;
    }

    public function getFailReason(): DistributionFailReason
    {
        return $this->failReason;
    }

    public function setFailReason(DistributionFailReason $failReason): static
    {
        $this->failReason = $failReason;

        return $this;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getAssetFile(): ?AssetFile
    {
        return $this->assetFile;
    }

    public function setAssetFile(?AssetFile $assetFile): void
    {
        $this->assetFile = $assetFile;
    }

    public function getExtSystem(): ExtSystem
    {
        // todo after release, make assetFile non nullable
        return $this->assetFile?->getExtSystem() ?? new ExtSystem();
    }
}
