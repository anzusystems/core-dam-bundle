<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastImportMode;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @extends AbstractAnzuRepository<Podcast>
 *
 * @method Podcast|null find($id, $lockMode = null, $lockVersion = null)
 * @method Podcast|null findOneBy(array $criteria, array $orderBy = null)
 */
class PodcastRepository extends AbstractAnzuRepository
{
    public function findOneLastMobile(): ?Podcast
    {
        return $this->findOneBy(
            [],
            [
                'attributes.mobileOrderPosition' => Order::Descending->value,
            ]
        );
    }

    public function findOneLastWeb(): ?Podcast
    {
        return $this->findOneBy(
            [],
            [
                'attributes.webOrderPosition' => Order::Descending->value,
            ]
        );
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findOneFrom(?string $idFrom = null, ?PodcastImportMode $mode = null): ?Podcast
    {
        $qb = $this->createQueryBuilder('entity')
            ->setMaxResults(1)
            ->orderBy('entity.id', Order::Ascending->value);

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
