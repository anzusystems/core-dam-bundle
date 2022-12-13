<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\JwVideo;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class JwVideoMediaUploadDto
{
    #[Serialize]
    private JwMediaMetadataDto $metadata;

    public function __construct()
    {
        $this->setMetadata(new JwMediaMetadataDto());
    }

    public function getMetadata(): JwMediaMetadataDto
    {
        return $this->metadata;
    }

    public function setMetadata(JwMediaMetadataDto $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }
}
