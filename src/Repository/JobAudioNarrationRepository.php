<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\JobAudioNarration;
use AnzuSystems\CoreDamBundle\Repository\AbstractAnzuRepository;

/**
 * @extends AbstractAnzuRepository<JobAudioNarration>
 *
 * @method JobAudioNarration|null find($id, $lockMode = null, $lockVersion = null)
 * @method JobAudioNarration|null findOneBy(array $criteria, array $orderBy = null)
 */
final class JobAudioNarrationRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return JobAudioNarration::class;
    }
}
