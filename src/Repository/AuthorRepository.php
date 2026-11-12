<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\App;
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
    public function findOneByNameAndExtSystem(string $name, ExtSystem $extSystem, ?string $identifier = null): ?Author
    {
        $criteria = [
            'name' => $name,
            'extSystem' => $extSystem,
        ];
        if (null !== $identifier) {
            $criteria['identifier'] = $identifier;
        }

        return $this->findOneBy($criteria);
    }

    /**
     * @return list<Author>
     */
    public function findByNormalizedNameAndExtSystem(string $nameNormalized, ExtSystem $extSystem, ?string $identifier = null): array
    {
        if (App::EMPTY_STRING === $nameNormalized) {
            return [];
        }

        $qb = $this->createQueryBuilder('author')
            ->addSelect('currentAuthor')
            ->leftJoin('author.currentAuthors', 'currentAuthor')
            ->where('author.nameNormalized = :nameNormalized')
            ->andWhere('IDENTITY(author.extSystem) = :extSystemId')
            ->setParameter('nameNormalized', $nameNormalized)
            ->setParameter('extSystemId', (int) $extSystem->getId())
        ;
        if (null !== $identifier) {
            $qb->andWhere('author.identifier = :identifier')->setParameter('identifier', $identifier);
        }

        return $qb->getQuery()->getResult();
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
}
