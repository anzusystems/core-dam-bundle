<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\ExtSystem;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class SingleUseUsage
{
    #[Serialize]
    private int $totalCount = App::ZERO;

    #[Serialize]
    private int $otherHolderCount = App::ZERO;

    #[Serialize]
    private bool $blocked = false;

    #[Serialize(type: SingleUseUsagePlace::class)]
    private Collection $places;

    public function __construct()
    {
        $this->setPlaces(new ArrayCollection());
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function setTotalCount(int $totalCount): self
    {
        $this->totalCount = $totalCount;

        return $this;
    }

    public function getOtherHolderCount(): int
    {
        return $this->otherHolderCount;
    }

    public function setOtherHolderCount(int $otherHolderCount): self
    {
        $this->otherHolderCount = $otherHolderCount;

        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function setBlocked(bool $blocked): self
    {
        $this->blocked = $blocked;

        return $this;
    }

    /**
     * @return Collection<array-key, SingleUseUsagePlace>
     */
    public function getPlaces(): Collection
    {
        return $this->places;
    }

    /**
     * @param Collection<array-key, SingleUseUsagePlace> $places
     */
    public function setPlaces(Collection $places): self
    {
        $this->places = $places;

        return $this;
    }
}
