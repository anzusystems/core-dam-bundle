<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class ImageTransferResultDto
{
    /**
     * @var list<string>
     */
    #[Serialize]
    private array $transferred = [];

    #[Serialize(type: ImageUsageConflictDto::class)]
    private Collection $notHeldByFrom;

    public function __construct()
    {
        $this->notHeldByFrom = new ArrayCollection();
    }

    /**
     * @return list<string>
     */
    public function getTransferred(): array
    {
        return $this->transferred;
    }

    public function addTransferred(string $damId): self
    {
        $this->transferred[] = $damId;

        return $this;
    }

    /**
     * @return Collection<array-key, ImageUsageConflictDto>
     */
    public function getNotHeldByFrom(): Collection
    {
        return $this->notHeldByFrom;
    }

    public function addNotHeldByFrom(ImageUsageConflictDto $conflict): self
    {
        $this->notHeldByFrom->add($conflict);

        return $this;
    }
}
