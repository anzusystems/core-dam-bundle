<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetFileProperties
{
    #[ORM\Column(type: Types::JSON)]
    private array $distributesInServices;

    #[ORM\Column(type: Types::JSON)]
    private array $slotNames;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $fromRss;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private int $width;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private int $height;

    public function __construct()
    {
        $this->setDistributesInServices([]);
        $this->setSlotNames([]);
        $this->setFromRss(false);
        $this->setWidth(0);
        $this->setHeight(0);
    }

    public function getDistributesInServices(): array
    {
        return $this->distributesInServices;
    }

    public function setDistributesInServices(array $distributesInServices): self
    {
        $this->distributesInServices = $distributesInServices;

        return $this;
    }

    public function getSlotNames(): array
    {
        return $this->slotNames;
    }

    public function setSlotNames(array $slotNames): self
    {
        $this->slotNames = $slotNames;

        return $this;
    }

    public function isFromRss(): bool
    {
        return $this->fromRss;
    }

    public function setFromRss(bool $fromRss): self
    {
        $this->fromRss = $fromRss;

        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }
}
