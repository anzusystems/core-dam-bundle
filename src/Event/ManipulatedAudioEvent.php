<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

final readonly class ManipulatedAudioEvent
{
    public function __construct(
        private string $audioId,
        private string $publicPath,
        private string $extSystemSlug,
    ) {
    }

    public function getAudioId(): string
    {
        return $this->audioId;
    }

    public function getPublicPath(): string
    {
        return $this->publicPath;
    }

    public function getExtSystemSlug(): string
    {
        return $this->extSystemSlug;
    }
}
