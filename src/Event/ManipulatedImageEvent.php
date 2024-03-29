<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

final readonly class ManipulatedImageEvent
{
    public function __construct(
        protected string $imageId,
        protected array $roiPositions,
        protected string $extSystem,
    ) {
    }

    public function getImageId(): string
    {
        return $this->imageId;
    }

    public function getRoiPositions(): array
    {
        return $this->roiPositions;
    }

    public function getExtSystem(): string
    {
        return $this->extSystem;
    }
}
