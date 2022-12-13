<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T of BaseIdentifiableInterface
 *
 * @method AssetFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetFile|null findOneBy(array $criteria, array $orderBy = null)
 */
abstract class AbstractAssetFileRepository extends AbstractAnzuRepository
{
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
     * @throws NonUniqueResultException
     */
    public function findProcessedByChecksum(string $checksum): ?AssetFile
    {
        return $this->createQueryBuilder('entity')
            ->where('entity.assetAttributes.checksum = :checksum')
            ->andWhere('entity.assetAttributes.status = :status')
            ->setParameter('checksum', $checksum, Types::STRING)
            ->setParameter('status', AssetFileProcessStatus::Processed)
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getByAssetAndFileVersionName(string $assetId, string $version): ?AssetFile
    {
        return $this->getByAssetIdQb($assetId)
            ->andWhere('assetHasFile.versionTitle = :version')
            ->setParameter('version', $version)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getDefaultByAsset(string $assetId): ?AssetFile
    {
        return $this->getByAssetIdQb($assetId)
            ->andWhere('assetHasFile.default = :true')
            ->setParameter('true', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getByAssetIdQb(string $assetId): QueryBuilder
    {
        return $this->createQueryBuilder('entity')
            ->innerJoin('entity.asset', 'assetHasFile')
            ->andWhere('IDENTITY(assetHasFile.asset) = :assetId')
            ->setParameter('assetId', $assetId);
    }
}
