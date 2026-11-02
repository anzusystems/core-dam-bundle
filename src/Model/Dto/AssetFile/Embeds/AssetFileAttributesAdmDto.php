<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\Embeds;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final class AssetFileAttributesAdmDto
{
    #[Serialize]
    private AssetFileProcessStatus $status;

    #[Serialize]
    private AssetFileFailedType $failReason;

    #[Serialize]
    private string $mimeType = '';

    #[Serialize]
    private int $size = 0;

    #[Serialize]
    private string $originFileName = '';

    #[Serialize]
    private string $takenOverFromId = '';

    #[Serialize]
    private ?DateTimeImmutable $firstUsedAt = null;

    #[Serialize]
    private string $usedByHolderName = '';

    #[Serialize]
    private string $usedByHolderId = '';

    #[Serialize]
    #[Assert\Url]
    #[Assert\Length(max: 2_048, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $originUrl = null;

    public function __construct()
    {
        $this->setStatus(AssetFileProcessStatus::Default);
        $this->setFailReason(AssetFileFailedType::Default);
    }

    /**
     * Takes the whole file, not just the embed: `firstUsedAt` lives on the entity while the holder it
     * belongs with lives on the attributes, and the picker reads them as one thing.
     */
    public static function getInstance(AssetFile $assetFile): self
    {
        $assetFileAttributes = $assetFile->getAssetAttributes();

        return (new self())
            ->setStatus($assetFileAttributes->getStatus())
            ->setMimeType($assetFileAttributes->getMimeType())
            ->setSize($assetFileAttributes->getSize())
            ->setOriginFileName($assetFileAttributes->getOriginFileName())
            ->setTakenOverFromId($assetFileAttributes->getTakenOverFromId())
            ->setFirstUsedAt($assetFile->getFirstUsedAt())
            ->setUsedByHolderName($assetFileAttributes->getUsedByHolderName())
            ->setUsedByHolderId($assetFileAttributes->getUsedByHolderId())
            ->setOriginUrl($assetFileAttributes->getOriginUrl())
            ->setFailReason($assetFileAttributes->getFailReason())
        ;
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

    public function getStatus(): AssetFileProcessStatus
    {
        return $this->status;
    }

    public function setStatus(AssetFileProcessStatus $status): self
    {
        $this->status = $status;

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

    public function getOriginFileName(): string
    {
        return $this->originFileName;
    }

    public function setOriginFileName(string $originFileName): self
    {
        $this->originFileName = $originFileName;

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

    public function getFirstUsedAt(): ?DateTimeImmutable
    {
        return $this->firstUsedAt;
    }

    public function setFirstUsedAt(?DateTimeImmutable $firstUsedAt): self
    {
        $this->firstUsedAt = $firstUsedAt;

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

    public function getUsedByHolderId(): string
    {
        return $this->usedByHolderId;
    }

    public function setUsedByHolderId(string $usedByHolderId): self
    {
        $this->usedByHolderId = $usedByHolderId;

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
}
