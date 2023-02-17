<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\PodcastEpisode;

use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastEpisodeStatus;

final class PodcastEpisodeStatusManager extends PodcastEpisodeManager
{
    public function toImported(PodcastEpisode $podcastEpisode, bool $flush = true): PodcastEpisode
    {
        return $this->setStatus($podcastEpisode, PodcastEpisodeStatus::Imported, $flush);
    }

    public function toImportFailed(PodcastEpisode $podcastEpisode, bool $flush = true): PodcastEpisode
    {
        return $this->setStatus($podcastEpisode, PodcastEpisodeStatus::ImportFailed, $flush);
    }

    public function toConflict(PodcastEpisode $podcastEpisode, bool $flush = true): PodcastEpisode
    {
        return $this->setStatus($podcastEpisode, PodcastEpisodeStatus::ImportFailed, $flush);
    }

    private function setStatus(PodcastEpisode $podcastEpisode, PodcastEpisodeStatus $status, bool $flush = true): PodcastEpisode
    {
        $podcastEpisode->getAttributes()->setLastImportStatus($status);

        return $this->updateExisting($podcastEpisode, $flush);
    }
}
