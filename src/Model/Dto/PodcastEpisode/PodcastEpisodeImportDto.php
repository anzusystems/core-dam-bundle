<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;

final readonly class PodcastEpisodeImportDto
{
    public function __construct(
        private PodcastEpisode $episode,
        private bool $newlyImported
    ) {
    }

    public function getEpisode(): PodcastEpisode
    {
        return $this->episode;
    }

    public function isNewlyImported(): bool
    {
        return $this->newlyImported;
    }
}
