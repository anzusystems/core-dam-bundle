<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

/**
 * @extends AbstractAnzuRepository<Author>
 *
 * @method Author|null find($id, $lockMode = null, $lockVersion = null)
 * @method Author|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AuthorRepository extends AbstractAnzuRepository
{
    /**
     * @return list<string>
     */
    public function findIdsByNameAndExtSystem(string $name, ExtSystem $extSystem): array
    {
        return $this->createQueryBuilder('author')
            ->select('author.id')
            ->where('author.name = :name')
            ->andWhere('IDENTITY(author.extSystem) = :extSystemId')
            ->setParameter('name', $name)
            ->setParameter('extSystemId', (int) $extSystem->getId())
            ->getQuery()
            ->getSingleColumnResult();
    }

    protected function getEntityClass(): string
    {
        return Author::class;
    }
}
