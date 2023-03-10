<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Model\Dto\Podcast;

use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;

final readonly class PodcastImportIteratorDto
{
    public function __construct(
        private Podcast $podcast,
        private Item $item,
    ) {
    }

    public function getPodcast(): Podcast
    {
        return $this->podcast;
    }

    public function getItem(): Item
    {
        return $this->item;
    }
}