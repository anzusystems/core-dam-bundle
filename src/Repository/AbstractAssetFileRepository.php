<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T of AssetFile
 *
 * @method AssetFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetFile|null findOneBy(array $criteria, array $orderBy = null)
 */
abstract class AbstractAssetFileRepository extends AbstractAnzuRepository
{
    public function findAllProcessed(int $limit, ?string $idFrom = null): Collection
    {
        $qb = $this->createQueryBuilder('entity')
            ->andWhere('entity.assetAttributes.status = :status')
            ->setParameter('status', AssetFileProcessStatus::Processed)
            ->addOrderBy('entity.id', Criteria::ASC)
            ->setMaxResults($limit);

        if ($idFrom) {
            $qb->andWhere('entity.id > :id')
                ->setParameter('id', $idFrom);
        }

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findProcessedById(string $id): ?AssetFile
    {
        return $this->createQueryBuilder('entity')
            ->andWhere('entity.id = :id')
            ->andWhere('entity.assetAttributes.status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', AssetFileProcessStatus::Processed)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string|null $excludeAssetId skip files belonging to this asset — a file is never a duplicate of
     *                                     its own asset (e.g. a TTS regen whose new audio is byte-identical to
     *                                     the asset's current master)
     *
     * @throws NonUniqueResultException
     */
    public function findProcessedByChecksumAndLicence(string $checksum, AssetLicence $licence, ?string $excludeAssetId = null): ?AssetFile
    {
        $qb = $this->createQueryBuilder('entity')
            ->where('entity.assetAttributes.checksum = :checksum')
            ->andWhere('entity.assetAttributes.status = :status')
            ->andWhere('IDENTITY(entity.licence) = :licenceId')
            ->setParameter('checksum', $checksum, Types::STRING)
            ->setParameter('status', AssetFileProcessStatus::Processed)
            ->setParameter('licenceId', $licence->getId())
        ;
        if (null !== $excludeAssetId) {
            $qb->andWhere('IDENTITY(entity.asset) != :excludeAssetId')
                ->setParameter('excludeAssetId', $excludeAssetId);
        }

        return $qb
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /**
     * QB for the asset's *live* files. Grace-demoted files (expireAt set — detached from their slot during a
     * TTS regen swap but still FK-attached until the reaper runs) are excluded, so an FK-based enumeration can
     * never resurface a superseded file as current. See {@see AssetSlotFactory::replaceSlotFile()}.
     */
    public function getByAssetIdQb(string $assetId): QueryBuilder
    {
        return $this->createQueryBuilder('entity')
            ->andWhere('IDENTITY(entity.asset) = :assetId')
            ->andWhere('entity.expireAt IS NULL')
            ->setParameter('assetId', $assetId);
    }

    /**
     * @return Collection<int, T>
     */
    public function findToDelete(DateTimeInterface $createdAtUntil, int $limit): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->leftJoin('entity.asset', 'asset')
                ->andWhere('entity.assetAttributes.status in (:statuses)')
                ->andWhere('entity.createdAt < :createdAtUntil')
                ->andWhere('asset.mainFile IS NULL OR asset.mainFile != entity')
                ->setParameter('statuses', [AssetFileProcessStatus::Duplicate, AssetFileProcessStatus::Failed])
                ->setParameter('createdAtUntil', $createdAtUntil->format(DATE_ATOM))
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
        );
    }
}
