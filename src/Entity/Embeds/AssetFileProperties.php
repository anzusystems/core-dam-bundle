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
    private int $pixels;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private int $shortestDimension;

    public function __construct()
    {
        $this->setDistributesInServices([]);
        $this->setSlotNames([]);
        $this->setFromRss(false);
        $this->setPixels(0);
        $this->setShortestDimension(0);
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

    public function getPixels(): int
    {
        return $this->pixels;
    }

    public function setPixels(int $pixels): self
    {
        $this->pixels = $pixels;

        return $this;
    }

    public function getShortestDimension(): int
    {
        return $this->shortestDimension;
    }

    public function setShortestDimension(int $shortestDimension): self
    {
        $this->shortestDimension = $shortestDimension;

        return $this;
    }
}
