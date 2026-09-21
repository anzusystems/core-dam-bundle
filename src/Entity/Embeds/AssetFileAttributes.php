<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Doctrine\Type\OriginExternalProviderType;
use AnzuSystems\CoreDamBundle\Doctrine\Type\OriginStorageType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginExternalProvider;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginStorage;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetFileAttributes
{
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $checksum;

    #[ORM\Column(type: Types::STRING, length: 36)]
    #[Serialize]
    private string $originAssetId;

    /**
     * Root of the take-over chain: id of the AssetFile this one was physically copied from into another
     * licence, empty for originals. Soft link without a FK on purpose — licence retention deletes the
     * agency original while its take-overs live on.
     */
    #[ORM\Column(type: Types::STRING, length: 36, options: ['default' => ''])]
    #[Serialize]
    private string $takenOverFromId;

    /**
     * Effective holder of the photo — the pair a picker compares against when a single use licence allows
     * only one user. Empty means the photo is free.
     */
    #[ORM\Column(type: Types::STRING, length: 64, options: ['default' => ''])]
    #[Serialize]
    private string $usedByHolderName;

    #[ORM\Column(type: Types::STRING, length: 64, options: ['default' => ''])]
    #[Serialize]
    private string $usedByHolderId;

    /**
     * When the holder above becomes due for a usage check, so that a claim CMS never committed does not hold
     * the photo forever. Null once the check confirmed the use, and on release.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $usedByCheckAfter = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $filePath;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $originFileName;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $mimeType;

    #[ORM\Column(type: Types::STRING, length: 127)]
    private string $convertToMime;

    #[ORM\Column(type: Types::BIGINT)]
    private int $size;

    #[ORM\Column(type: Types::STRING, length: 2_048, nullable: true)]
    private ?string $originUrl;

    #[ORM\Column(type: OriginExternalProviderType::NAME, length: 255, nullable: true)]
    private ?OriginExternalProvider $originExternalProvider = null;

    #[ORM\Column(type: OriginStorageType::NAME, length: 255, nullable: true)]
    private ?OriginStorage $originStorage = null;

    #[ORM\Column(enumType: AssetFileProcessStatus::class)]
    private AssetFileProcessStatus $status;

    #[ORM\Column(enumType: AssetFileFailedType::class)]
    private AssetFileFailedType $failReason;

    #[ORM\Column(enumType: AssetFileCreateStrategy::class)]
    private AssetFileCreateStrategy $createStrategy;

    public function __construct()
    {
        $this->setStatus(AssetFileProcessStatus::Default);
        $this->setChecksum('');
        $this->setFilePath('');
        $this->setOriginFileName('');
        $this->setMimeType('');
        $this->setOriginAssetId('');
        $this->setTakenOverFromId('');
        $this->setUsedByHolderName(App::EMPTY_STRING);
        $this->setUsedByHolderId(App::EMPTY_STRING);
        $this->setUsedByCheckAfter(null);
        $this->setOriginUrl(null);
        $this->setOriginExternalProvider(null);
        $this->setOriginStorage(null);
        $this->setSize(0);
        $this->setFailReason(AssetFileFailedType::None);
        $this->setCreateStrategy(AssetFileCreateStrategy::Default);
        $this->setConvertToMime('');
    }

    public function getStatus(): AssetFileProcessStatus
    {
        return $this->status;
    }

    public function setStatus(AssetFileProcessStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function setChecksum(string $checksum): self
    {
        $this->checksum = $checksum;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getOriginFileName(): string
    {
        return $this->originFileName;
    }

    public function setOriginFileName(string $originFileName): self
    {
        $this->originFileName = $originFileName;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getOriginUrl(): ?string
    {
        return $this->originUrl;
    }

    public function setOriginUrl(?string $originUrl): self
    {
        $this->originUrl = $originUrl;

        return $this;
    }

    public function getOriginExternalProvider(): ?OriginExternalProvider
    {
        return $this->originExternalProvider;
    }

    public function setOriginExternalProvider(?OriginExternalProvider $originExternalProvider): self
    {
        $this->originExternalProvider = $originExternalProvider;

        return $this;
    }

    public function getFailReason(): AssetFileFailedType
    {
        return $this->failReason;
    }

    public function setFailReason(AssetFileFailedType $failReason): self
    {
        $this->failReason = $failReason;

        return $this;
    }

    public function getOriginAssetId(): string
    {
        return $this->originAssetId;
    }

    public function setOriginAssetId(string $originAssetId): self
    {
        $this->originAssetId = $originAssetId;

        return $this;
    }

    public function getTakenOverFromId(): string
    {
        return $this->takenOverFromId;
    }

    public function setTakenOverFromId(string $takenOverFromId): self
    {
        $this->takenOverFromId = $takenOverFromId;

        return $this;
    }

    public function getUsedByHolderName(): string
    {
        return $this->usedByHolderName;
    }

    public function setUsedByHolderName(string $usedByHolderName): self
    {
        $this->usedByHolderName = $usedByHolderName;

        return $this;
    }

    public function getUsedByCheckAfter(): ?DateTimeImmutable
    {
        return $this->usedByCheckAfter;
    }

    public function setUsedByCheckAfter(?DateTimeImmutable $usedByCheckAfter): self
    {
        $this->usedByCheckAfter = $usedByCheckAfter;

        return $this;
    }

    public function getUsedByHolderId(): string
    {
        return $this->usedByHolderId;
    }

    public function setUsedByHolderId(string $usedByHolderId): self
    {
        $this->usedByHolderId = $usedByHolderId;

        return $this;
    }

    public function getCreateStrategy(): AssetFileCreateStrategy
    {
        return $this->createStrategy;
    }

    public function setCreateStrategy(AssetFileCreateStrategy $createStrategy): self
    {
        $this->createStrategy = $createStrategy;

        return $this;
    }

    public function getOriginStorage(): ?OriginStorage
    {
        return $this->originStorage;
    }

    public function setOriginStorage(?OriginStorage $originStorage): self
    {
        $this->originStorage = $originStorage;

        return $this;
    }

    public function getConvertToMime(): string
    {
        return $this->convertToMime;
    }

    public function setConvertToMime(string $convertToMime): self
    {
        $this->convertToMime = $convertToMime;

        return $this;
    }
}
