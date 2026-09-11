<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Outcome of one usage sync. Empty conflicts means the whole request went through; a batch never fails as
 * a whole, so the non-conflicting part is committed either way.
 */
final class ImageUsageSyncResultDto
{
    #[Serialize(type: ImageUsageConflictDto::class)]
    private Collection $conflicts;

    public function __construct()
    {
        $this->setConflicts(new ArrayCollection());
    }

    /**
     * @param ImageUsageConflictDto[] $conflicts
     */
    public static function getInstance(array $conflicts): self
    {
        return (new self())
            ->setConflicts(new ArrayCollection(array_values($conflicts)));
    }

    /**
     * @return Collection<array-key, ImageUsageConflictDto>
     */
    public function getConflicts(): Collection
    {
        return $this->conflicts;
    }

    public function setConflicts(Collection $conflicts): self
    {
        $this->conflicts = $conflicts;

        return $this;
    }
}
