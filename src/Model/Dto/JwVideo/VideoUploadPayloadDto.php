<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\JwVideo;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class VideoUploadPayloadDto
{
    #[Serialize]
    private string $id;

    #[Serialize(serializedName: 'upload_token')]
    private string $uploadToken;

    #[Serialize(serializedName: 'upload_id')]
    private string $uploadId;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUploadToken(): string
    {
        return $this->uploadToken;
    }

    public function setUploadToken(string $uploadToken): self
    {
        $this->uploadToken = $uploadToken;

        return $this;
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
    }

    public function setUploadId(string $uploadId): self
    {
        $this->uploadId = $uploadId;

        return $this;
    }
}
