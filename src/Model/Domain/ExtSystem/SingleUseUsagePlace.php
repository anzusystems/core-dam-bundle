<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\ExtSystem;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;

final class SingleUseUsagePlace
{
    #[Serialize]
    private string $resourceName = App::EMPTY_STRING;

    #[Serialize]
    private int $resourceId = App::ZERO;

    #[Serialize]
    private int $imageId = App::ZERO;

    #[Serialize]
    private DateTimeImmutable $pickedAt;

    public function __construct()
    {
        $this->setPickedAt(App::getAppDate());
    }

    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    public function setResourceName(string $resourceName): self
    {
        $this->resourceName = $resourceName;

        return $this;
    }

    public function getResourceId(): int
    {
        return $this->resourceId;
    }

    public function setResourceId(int $resourceId): self
    {
        $this->resourceId = $resourceId;

        return $this;
    }

    public function getImageId(): int
    {
        return $this->imageId;
    }

    public function setImageId(int $imageId): self
    {
        $this->imageId = $imageId;

        return $this;
    }

    public function getPickedAt(): DateTimeImmutable
    {
        return $this->pickedAt;
    }

    public function setPickedAt(DateTimeImmutable $pickedAt): self
    {
        $this->pickedAt = $pickedAt;

        return $this;
    }
}
