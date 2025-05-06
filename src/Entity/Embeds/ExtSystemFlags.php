<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class ExtSystemFlags
{
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $checkImageUsedOnDelete = false;

    public function isCheckImageUsedOnDelete(): bool
    {
        return $this->checkImageUsedOnDelete;
    }

    public function setCheckImageUsedOnDelete(bool $checkImageUsedOnDelete): self
    {
        $this->checkImageUsedOnDelete = $checkImageUsedOnDelete;

        return $this;
    }
}
