<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Keyword;

/**
 * @extends AbstractAnzuRepository<Keyword>
 *
 * @method Keyword|null find($id, $lockMode = null, $lockVersion = null)
 * @method Keyword|null findOneBy(array $criteria, array $orderBy = null)
 */
final class KeywordRepository extends AbstractAnzuRepository
{
    public function findOneByNameAndExtSystem(string $name, ExtSystem $extSystem): ?Keyword
    {
        return $this->findOneBy([
            'name' => $name,
            'extSystem' => $extSystem,
        ]);
    }

    /**
     * @return list<string>
     */
    public function findIdsByNameAndExtSystem(string $name, ExtSystem $extSystem): array
    {
        return $this->createQueryBuilder('keyword')
            ->select('keyword.id')
            ->where('keyword.name = :name')
            ->andWhere('IDENTITY(keyword.extSystem) = :extSystemId')
            ->setParameter('name', $name)
            ->setParameter('extSystemId', (int) $extSystem->getId())
            ->getQuery()
            ->getSingleColumnResult();
    }

    protected function getEntityClass(): string
    {
        return Keyword::class;
    }
}
