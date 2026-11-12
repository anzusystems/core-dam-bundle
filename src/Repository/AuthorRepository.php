<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends AbstractAnzuRepository<Author>
 *
 * @method Author|null find($id, $lockMode = null, $lockVersion = null)
 * @method Author|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AuthorRepository extends AbstractAnzuRepository
{
    /**
     * @return list<Author>
     */
    public function findByNameAndExtSystem(string $name, ExtSystem $extSystem, ?string $identifier = null): array
    {
        return $this->createMatchQueryBuilder($extSystem, $identifier)
            ->andWhere('author.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Author>
     */
    public function findByNormalizedNameAndExtSystem(string $nameNormalized, ExtSystem $extSystem, ?string $identifier = null): array
    {
        if (App::EMPTY_STRING === $nameNormalized) {
            return [];
        }

        return $this->createMatchQueryBuilder($extSystem, $identifier)
            ->andWhere('author.nameNormalized = :nameNormalized')
            ->setParameter('nameNormalized', $nameNormalized)
            ->getQuery()
            ->getResult();
    }

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

    private function createMatchQueryBuilder(ExtSystem $extSystem, ?string $identifier): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('author')
            ->addSelect('currentAuthor')
            ->leftJoin('author.currentAuthors', 'currentAuthor')
            ->where('IDENTITY(author.extSystem) = :extSystemId')
            ->setParameter('extSystemId', (int) $extSystem->getId())
            ->orderBy('author.flags.reviewed', Order::Descending->value);
        if (null !== $identifier) {
            $queryBuilder->andWhere('author.identifier = :identifier')->setParameter('identifier', $identifier);
        }

        return $queryBuilder;
    }
}
