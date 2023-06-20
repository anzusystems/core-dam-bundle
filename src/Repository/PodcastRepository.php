<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastImportMode;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @extends AbstractAnzuRepository<Podcast>
 *
 * @method Podcast|null find($id, $lockMode = null, $lockVersion = null)
 * @method Podcast|null findOneBy(array $criteria, array $orderBy = null)
 */
final class PodcastRepository extends AbstractAnzuRepository
{
    /**
     * @throws NonUniqueResultException
     */
    public function findOneFrom(?string $idFrom = null, ?PodcastImportMode $mode = null): ?Podcast
    {
        $qb = $this->createQueryBuilder('entity')
            ->setMaxResults(1)
            ->orderBy('entity.id', Criteria::ASC);

        if ($mode) {
            $qb->andWhere('entity.attributes.mode = :mode')
                ->setParameter('mode', $mode->toString());
        }
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
