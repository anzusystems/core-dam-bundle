<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Traits;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait PositionTrait
{
    #[ORM\Column(type: Types::SMALLINT)]
    #[Serialize]
    private int $position = 0;

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
