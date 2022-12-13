<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\JwVideo;

use AnzuSystems\CoreDamBundle\Model\Enum\JwMediaStatus;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class JwVideoMediaGetDto
{
    #[Serialize]
    private JwMediaMetadataDetailDto $metadata;

    #[Serialize]
    private JwMediaStatus $status;

    #[Serialize(serializedName: 'error_message')]
    private ?string $errorMessage = null;

    public function __construct()
    {
        $this->setMetadata(new JwMediaMetadataDetailDto());
        $this->setStatus(JwMediaStatus::Default);
    }

    public function getMetadata(): JwMediaMetadataDto
    {
        return $this->metadata;
    }

    public function setMetadata(JwMediaMetadataDetailDto $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getStatus(): JwMediaStatus
    {
        return $this->status;
    }

    public function setStatus(JwMediaStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
