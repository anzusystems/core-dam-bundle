<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastImportMode;
use Doctrine\Common\Collections\Criteria;

/**
 * @extends AbstractAnzuRepository<Podcast>
 *
 * @method Podcast|null find($id, $lockMode = null, $lockVersion = null)
 * @method Podcast|null findOneBy(array $criteria, array $orderBy = null)
 */
final class PodcastRepository extends AbstractAnzuRepository
{
    public function findOneToImport(?string $idFrom = null): ?Podcast
    {
        $qb = $this->createQueryBuilder('entity')
            ->where('entity.attributes.mode IN (:modes)')
            ->setParameter('modes', array_map(
                fn (PodcastImportMode $mode): string => $mode->toString(),
                PodcastImportMode::getAllImportModes()
            ))
            ->setMaxResults(1)
            ->orderBy('entity.id', Criteria::ASC);

        if ($idFrom) {
            $qb->andWhere('entity.id > :idFrom')
                ->setParameter('idFrom', $idFrom)
            ;
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    protected function getEntityClass(): string
    {
        return Podcast::class;
    }
}
