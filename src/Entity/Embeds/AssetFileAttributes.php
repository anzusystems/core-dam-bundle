<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Doctrine\Type\OriginExternalProviderType;
use AnzuSystems\CoreDamBundle\Doctrine\Type\OriginStorageType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginExternalProvider;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginStorage;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetFileAttributes
{
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $checksum;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $originAssetId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $filePath;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $originFileName;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $mimeType;

    #[ORM\Column(type: Types::BIGINT)]
    private int $size;

    #[ORM\Column(type: Types::STRING, length: 2_048, nullable: true)]
    private ?string $originUrl;

    #[ORM\Column(type: OriginExternalProviderType::NAME, nullable: true)]
    private ?OriginExternalProvider $originExternalProvider = null;

    #[ORM\Column(type: OriginStorageType::NAME, nullable: true)]
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
        $this->setOriginUrl(null);
        $this->setOriginExternalProvider(null);
        $this->setOriginStorage(null);
        $this->setSize(0);
        $this->setFailReason(AssetFileFailedType::None);
        $this->setCreateStrategy(AssetFileCreateStrategy::Default);
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
}
