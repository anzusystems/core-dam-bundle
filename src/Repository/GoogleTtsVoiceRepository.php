<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\GoogleTtsVoice;

/**
 * @extends AbstractAnzuRepository<GoogleTtsVoice>
 *
 * @method GoogleTtsVoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method GoogleTtsVoice|null findOneBy(array $criteria, array $orderBy = null)
 */
final class GoogleTtsVoiceRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return GoogleTtsVoice::class;
    }
}
