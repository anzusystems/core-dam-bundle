<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\JwVideo;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class VideoUploadLinks
{
    #[Serialize(type: VideoUploadLinkItem::class)]
    private ArrayCollection $parts;

    public function __construct()
    {
        $this->parts = new ArrayCollection();
    }

    /**
     * @return ArrayCollection<int, VideoUploadLinkItem>
     */
    public function getParts(): Collection
    {
        return $this->parts;
    }

    public function setParts(ArrayCollection $parts): self
    {
        $this->parts = $parts;

        return $this;
    }
}
