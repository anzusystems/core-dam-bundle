<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

final readonly class JwVideoThumbnailPosterMessage
{
    public function __construct(
        private string $thumbnailId,
        private string $distribService
    ) {
    }

    public function getThumbnailId(): string
    {
        return $this->thumbnailId;
    }

    public function getDistribService(): string
    {
        return $this->distribService;
    }
}
