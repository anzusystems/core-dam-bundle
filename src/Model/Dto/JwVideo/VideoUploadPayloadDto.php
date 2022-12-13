<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\JwVideo;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class VideoUploadPayloadDto
{
    #[Serialize]
    private string $id;

    #[Serialize(serializedName: 'upload_link')]
    private string $uploadLink;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUploadLink(): string
    {
        return $this->uploadLink;
    }

    public function setUploadLink(string $uploadLink): self
    {
        $this->uploadLink = $uploadLink;

        return $this;
    }
}
