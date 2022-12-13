<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Traits;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait ReviewedTrait
{
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $reviewed = false;

    public function isReviewed(): bool
    {
        return $this->reviewed;
    }

    public function setReviewed(bool $reviewed): self
    {
        $this->reviewed = $reviewed;

        return $this;
    }
}
