<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class AssetSlotFlags
{
    #[ORM\Column(name: 'is_default', type: Types::BOOLEAN)]
    private bool $default;

    #[ORM\Column(name: 'is_main', type: Types::BOOLEAN)]
    private bool $main;

    public function __construct()
    {
        $this->setMain(false);
        $this->setDefault(false);
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function isMain(): bool
    {
        return $this->main;
    }

    public function setMain(bool $main): self
    {
        $this->main = $main;

        return $this;
    }
}
