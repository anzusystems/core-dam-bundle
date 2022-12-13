<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Unsplash;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;

final class UnsplashListDto
{
    #[Serialize]
    private int $total;

    #[Serialize(serializedName: 'total_pages')]
    private int $totalPages;

    #[Serialize(type: UnsplashImageDto::class)]
    private ArrayCollection $results;

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function setTotalPages(int $totalPages): self
    {
        $this->totalPages = $totalPages;

        return $this;
    }

    /**
     * @return ArrayCollection<int, UnsplashImageDto>
     */
    public function getResults(): ArrayCollection
    {
        return $this->results;
    }

    public function setResults(ArrayCollection $results): self
    {
        $this->results = $results;

        return $this;
    }
}
