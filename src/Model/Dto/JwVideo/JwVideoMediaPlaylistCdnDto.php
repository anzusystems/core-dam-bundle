<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\JwVideo;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class JwVideoMediaPlaylistCdnDto
{
    #[Serialize(type: JwVideoMediaPlaylistSourceCdnDto::class)]
    private Collection $sources;

    public function __construct()
    {
        $this->setSources(new ArrayCollection());
    }

    /**
     * @return Collection<int, JwVideoMediaPlaylistSourceCdnDto>
     */
    public function getSources(): Collection
    {
        return $this->sources;
    }

    /**
     * @param Collection<int, JwVideoMediaPlaylistSourceCdnDto>  $sources
     */
    public function setSources(Collection $sources): self
    {
        $this->sources = $sources;

        return $this;
    }

}
