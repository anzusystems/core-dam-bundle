<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;

final class JobImageCopyManager extends AbstractManager
{
    public function create(JobImageCopy $imageCopy, bool $flush = true): JobImageCopy
    {
        $this->trackCreation($imageCopy);
        foreach ($imageCopy->getItems() as $item) {
            $item->setJob($imageCopy);
            $this->entityManager->persist($item);
        }

        $this->entityManager->persist($imageCopy);
        $this->flush($flush);

        return $imageCopy;
    }
}
