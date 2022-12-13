<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Podcast;

use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastLastImportStatus;

final class PodcastStatusManager extends PodcastManager
{
    public function toImported(Podcast $podcast, bool $flush = true): Podcast
    {
        return $this->setStatus($podcast, PodcastLastImportStatus::imported, $flush);
    }

    public function toImportFailed(Podcast $podcast, bool $flush = true): Podcast
    {
        return $this->setStatus($podcast, PodcastLastImportStatus::importFailed, $flush);
    }

    private function setStatus(Podcast $podcast, PodcastLastImportStatus $status, bool $flush = true): Podcast
    {
        $podcast->getAttributes()->setLastImportStatus($status);

        return $this->updateExisting($podcast, $flush);
    }
}
