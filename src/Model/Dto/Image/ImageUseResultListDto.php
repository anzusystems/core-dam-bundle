<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class ImageUseResultListDto
{
    #[Serialize(type: ImageUseResultDto::class)]
    private Collection $results;

    public function __construct()
    {
        $this->setResults(new ArrayCollection());
    }

    /**
     * @param Collection<array-key, ImageUseResultDto> $results
     */
    public static function getInstance(Collection $results): self
    {
        return (new self())->setResults($results);
    }

    /**
     * @return Collection<array-key, ImageUseResultDto>
     */
    public function getResults(): Collection
    {
        return $this->results;
    }

    /**
     * @param Collection<array-key, ImageUseResultDto> $results
     */
    public function setResults(Collection $results): self
    {
        $this->results = $results;

        return $this;
    }
}
