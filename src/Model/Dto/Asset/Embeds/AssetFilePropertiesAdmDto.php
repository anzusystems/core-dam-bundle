<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileProperties;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetFilePropertiesAdmDto
{
    private array $distributesInServices;
    private array $slotNames;
    private bool $fromRss;
    private int $width;
    private int $height;

    public static function getInstance(AssetFileProperties $properties): self
    {
        return (new self())
            ->setDistributesInServices($properties->getDistributesInServices())
            ->setSlotNames($properties->getSlotNames())
            ->setFromRss($properties->isFromRss())
            ->setWidth($properties->getWidth())
            ->setHeight($properties->getHeight());
    }

    #[Serialize]
    public function getDistributesInServices(): array
    {
        return $this->distributesInServices;
    }

    public function setDistributesInServices(array $distributesInServices): self
    {
        $this->distributesInServices = $distributesInServices;

        return $this;
    }

    #[Serialize]
    public function getSlotNames(): array
    {
        return $this->slotNames;
    }

    public function setSlotNames(array $slotNames): self
    {
        $this->slotNames = $slotNames;

        return $this;
    }

    #[Serialize]
    public function isFromRss(): bool
    {
        return $this->fromRss;
    }

    public function setFromRss(bool $fromRss): self
    {
        $this->fromRss = $fromRss;

        return $this;
    }

    #[Serialize]
    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    #[Serialize]
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
