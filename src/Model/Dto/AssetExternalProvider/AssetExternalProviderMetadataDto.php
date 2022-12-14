<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

class AssetExternalProviderMetadataDto
{
    protected string $authorName;

    #[Serialize]
    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): self
    {
        $this->authorName = $authorName;

        return $this;
    }
}
