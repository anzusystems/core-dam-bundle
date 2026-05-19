<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\ElevenlabsVoice;

/**
 * @extends AbstractAnzuRepository<ElevenlabsVoice>
 *
 * @method ElevenlabsVoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method ElevenlabsVoice|null findOneBy(array $criteria, array $orderBy = null)
 */
final class ElevenlabsVoiceRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return ElevenlabsVoice::class;
    }
}
