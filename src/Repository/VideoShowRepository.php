<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\VideoShow;

/**
 * @extends AbstractAnzuRepository<VideoShow>
 *
 * @method VideoShow|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoShow|null findOneBy(array $criteria, array $orderBy = null)
 */
final class VideoShowRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return VideoShow::class;
    }
}
