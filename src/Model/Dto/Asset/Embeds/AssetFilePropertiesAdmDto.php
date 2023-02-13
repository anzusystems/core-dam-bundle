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
    private int $pixels;
    private int $shortestDimension;

    public static function getInstance(AssetFileProperties $properties): self
    {
        return (new self())
            ->setDistributesInServices($properties->getDistributesInServices())
            ->setSlotNames($properties->getSlotNames())
            ->setFromRss($properties->isFromRss())
            ->setPixels($properties->getPixels())
            ->setShortestDimension($properties->getShortestDimension());
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
    public function getPixels(): int
    {
        return $this->pixels;
    }

    public function setPixels(int $pixels): self
    {
        $this->pixels = $pixels;

        return $this;
    }

    #[Serialize]
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
