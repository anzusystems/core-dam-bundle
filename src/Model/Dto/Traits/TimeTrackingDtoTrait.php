<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Traits;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;

trait TimeTrackingDtoTrait
{
    protected DateTimeImmutable $createdAt;
    protected DateTimeImmutable $modifiedAt;

    #[Serialize]
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[Serialize]
    public function getModifiedAt(): DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(DateTimeImmutable $modifiedAt): static
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }
}
