<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\JwVideo;

use AnzuSystems\CoreDamBundle\Model\Enum\JwMediaStatus;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class JwVideoThumbnail
{
    #[Serialize]
    private string $id;

    #[Serialize]
    private JwMediaStatus $status;

    #[Serialize(serializedName: 'error_message')]
    private ?string $errorMessage = null;

    public function __construct()
    {
        $this->setId('');
        $this->setStatus(JwMediaStatus::Default);
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
