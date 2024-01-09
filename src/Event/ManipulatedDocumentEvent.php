<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

final readonly class ManipulatedDocumentEvent
{
    public function __construct(
        protected string $documentId,
        protected string $publicPath,
        protected string $extSystemSlug,
    ) {
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
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
