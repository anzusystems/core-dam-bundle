<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends AbstractAnzuRepository<Asset>
 *
 * @method Asset|null find($id, $lockMode = null, $lockVersion = null)
 * @method Asset|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method Asset|null findProcessedById(string $id)
 * @method Asset|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class AssetRepository extends AbstractAnzuRepository
{
    public function findByAuthor(Author $author, string $fromId = '', int $limit = 100): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->innerJoin('entity.authors', 'author')
                ->where('author.id = :id')
                ->andWhere('entity.id > :fromId')
                ->setParameter('id', $author->getId())
                ->setParameter('fromId', $fromId)
                ->addOrderBy('entity.id', Order::Ascending->value)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }
    public function findByLicenceAndIds(AssetLicence $assetLicence, array $ids): Collection
    {
        return new ArrayCollection(
            $this->findBy(
                [
                    'licence' => $assetLicence,
                    'id' => $ids,
                ]
            )
        );
    }

    /**
     * @return Collection<int, Asset>
     */
    public function findToDelete(DateTimeInterface $createdAtUntil, int $limit): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->andWhere('entity.assetFlags.autoDeleteUnprocessed = :true')
                ->andWhere('entity.attributes.status = :draftStatus')
                ->andWhere('entity.createdAt < :createdAtUntil')
                ->setParameter('true', true)
                ->setParameter('draftStatus', AssetStatus::DRAFT)
                ->setParameter('createdAtUntil', $createdAtUntil->format(DATE_ATOM))
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }

    public function findByExtSystemAndIds(ExtSystem $extSystem, array $ids): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->innerJoin('entity.licence', 'licence')
                ->andWhere('entity.id in (:ids)')
                ->andWhere('IDENTITY(licence.extSystem) = :extSystemId')
                ->setParameter('extSystemId', $extSystem->getId())
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult()
        );
    }

    public function geAllByLicenceIds(array $licenceIds, int $limit, ?string $idFrom = null): Collection
    {
        $queryBuilder = $this->createQueryBuilder('entity')
            ->where('IDENTITY(entity.licence) in (:licenceIds)')
            ->setParameter('licenceIds', $licenceIds);

        if (is_string($idFrom)) {
            $queryBuilder
                ->andWhere('entity.id > :idFrom')
                ->setParameter('idFrom', $idFrom);
        }

        return new ArrayCollection(
            $queryBuilder
                ->setMaxResults($limit)
                ->orderBy('entity.id', Criteria::ASC)
                ->getQuery()
                ->getResult()
        );
    }

    protected function appendRebuildIndexQueryForExtSystem(QueryBuilder $queryBuilder, int $extSystemId): QueryBuilder
    {
        return $queryBuilder
            ->andWhere('IDENTITY(entity.extSystem) = :extSystemId')
            ->setParameter('extSystemId', $extSystemId);
    }

    protected function getEntityClass(): string
    {
        return Asset::class;
    }
}
