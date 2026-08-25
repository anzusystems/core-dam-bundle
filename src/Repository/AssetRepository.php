<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetLicenceAutoDelete;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use DateTimeImmutable;
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
    /**
     * `mainFile` is a JOINED-inheritance root Doctrine cannot proxy — join-fetch avoids one query per row.
     *
     * @return ArrayCollection<int|string, Asset>
     */
    public function getAllByIdIndexed(int | string ...$id): ArrayCollection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity', 'entity.id')
                ->leftJoin('entity.mainFile', 'mainFile')
                ->addSelect('mainFile')
                ->where('entity.id IN (:ids)')
                ->setParameter('ids', $id)
                ->orderBy('FIELD(entity.id, :ids)')
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * @return Collection<array-key, Asset>
     */
    public function findByIds(array $ids): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->where('entity.id in (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * @param list<string> $authorIds
     */
    public function findByAuthorIds(array $authorIds, string $fromId = '', int $limit = 100): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->distinct()
                ->innerJoin('entity.authors', 'author')
                ->where('author.id IN (:ids)')
                ->andWhere('entity.id > :fromId')
                ->setParameter('ids', $authorIds)
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

    /**
     * Retention condition lives in the query, not PHP, so a config change mid-run cannot resurrect a disabled licence.
     */
    public function findByLicenceRetention(AssetLicence $licence, DateTimeImmutable $createdBefore, int $limit, ?string $idFrom = null): Collection
    {
        $queryBuilder = $this->createQueryBuilder('entity')
            ->innerJoin('entity.licence', 'licence')
            ->where('IDENTITY(entity.licence) = :licenceId')
            ->andWhere('licence.autoDelete.active = :true')
            ->andWhere('licence.autoDelete.olderThanDays >= :minOlderThanDays')
            ->andWhere('entity.createdAt < :createdBefore')
            ->setParameter('licenceId', $licence->getId())
            ->setParameter('true', true)
            ->setParameter('minOlderThanDays', AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS)
            ->setParameter('createdBefore', $createdBefore);

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

    /**
     * @return Collection<int, Asset>
     */
    public function findAllByLicence(
        AssetLicence $licence,
        int $limit,
        string $idFrom = '',
        ?DateTimeImmutable $createdFrom = null,
        ?DateTimeImmutable $createdUntil = null,
    ): Collection {
        $queryBuilder = $this->createQueryBuilder('entity')
            ->where('IDENTITY(entity.licence) = :licenceId')
            ->setParameter('licenceId', $licence->getId())
            ->orderBy('entity.id', Criteria::ASC)
            ->setMaxResults($limit);

        if (false === ('' === $idFrom)) {
            $queryBuilder
                ->andWhere('entity.id > :idFrom')
                ->setParameter('idFrom', $idFrom);
        }

        if ($createdFrom instanceof DateTimeImmutable) {
            $queryBuilder
                ->andWhere('entity.createdAt >= :createdFrom')
                ->setParameter('createdFrom', $createdFrom);
        }

        if ($createdUntil instanceof DateTimeImmutable) {
            $queryBuilder
                ->andWhere('entity.createdAt <= :createdUntil')
                ->setParameter('createdUntil', $createdUntil);
        }

        return new ArrayCollection(
            $queryBuilder->getQuery()->getResult()
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
