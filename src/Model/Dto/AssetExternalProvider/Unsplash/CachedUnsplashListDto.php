<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class CachedUnsplashListDto
{
    #[Serialize]
    private bool $hasNextPage;

    #[Serialize]
    private array $ids;

    public function isHasNextPage(): bool
    {
        return $this->hasNextPage;
    }

    public function setHasNextPage(bool $hasNextPage): self
    {
        $this->hasNextPage = $hasNextPage;

        return $this;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function setIds(array $ids): self
    {
        $this->ids = $ids;

        return $this;
    }
}
