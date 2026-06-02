<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class SynthesizeResponseDto
{
    #[Serialize]
    private ?string $requestId = null;

    #[Serialize]
    private ?string $existingAssetId = null;

    #[Serialize]
    private ?string $assetId = null;

    #[Serialize]
    private DispatchStatus $status = DispatchStatus::Pending;

    public static function fromResult(DispatchResult $result): self
    {
        return (new self())
            ->setRequestId($result->requestId)
            ->setExistingAssetId($result->existingAssetId)
            ->setAssetId($result->getAssetId())
            ->setStatus($result->status);
    }

    public static function fromRequestId(string $requestId): self
    {
        return (new self())
            ->setRequestId($requestId)
            ->setStatus(DispatchStatus::Pending);
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $requestId;

        return $this;
    }

    public function getExistingAssetId(): ?string
    {
        return $this->existingAssetId;
    }

    public function setExistingAssetId(?string $existingAssetId): self
    {
        $this->existingAssetId = $existingAssetId;

        return $this;
    }

    public function getAssetId(): ?string
    {
        return $this->assetId;
    }

    public function setAssetId(?string $assetId): self
    {
        $this->assetId = $assetId;

        return $this;
    }

    public function getStatus(): DispatchStatus
    {
        return $this->status;
    }

    public function setStatus(DispatchStatus $status): self
    {
        $this->status = $status;

        return $this;
    }
}
