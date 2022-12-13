<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class UnsplashTagDto
{
    #[Serialize]
    private string $title;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
}
