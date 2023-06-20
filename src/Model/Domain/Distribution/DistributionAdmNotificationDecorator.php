<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Distribution;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DistributionAdmNotificationDecorator
{
    #[Serialize]
    private string $id;

    #[Serialize(serializedName: 'asset')]
    private string $assetId;

    #[Serialize(serializedName: 'assetFile')]
    private string $assetFileId;

    #[Serialize]
    private DistributionProcessStatus $status;

    public static function getInstance(Distribution $distribution): self
    {
        return (new self())
            ->setId((string) $distribution->getId())
            ->setAssetId($distribution->getAssetId())
            ->setAssetFileId($distribution->getAssetFileId())
            ->setStatus($distribution->getStatus())
        ;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function setAssetId(string $assetId): self
    {
        $this->assetId = $assetId;

        return $this;
    }

    public function getAssetFileId(): string
    {
        return $this->assetFileId;
    }

    public function setAssetFileId(string $assetFileId): self
    {
        $this->assetFileId = $assetFileId;

        return $this;
    }

    public function getStatus(): DistributionProcessStatus
    {
        return $this->status;
    }

    public function setStatus(DistributionProcessStatus $status): self
    {
        $this->status = $status;

        return $this;
    }
}
