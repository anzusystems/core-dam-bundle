<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
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
     * @throws NonUniqueResultException
     */
    public function findProcessedByChecksumAndLicence(string $checksum, AssetLicence $licence): ?AssetFile
    {
        return $this->createQueryBuilder('entity')
            ->where('entity.assetAttributes.checksum = :checksum')
            ->andWhere('entity.assetAttributes.status = :status')
            ->andWhere('IDENTITY(entity.licence) = :licenceId')
            ->setParameter('checksum', $checksum, Types::STRING)
            ->setParameter('status', AssetFileProcessStatus::Processed)
            ->setParameter('licenceId', $licence->getId())
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    public function getByAssetIdQb(string $assetId): QueryBuilder
    {
        return $this->createQueryBuilder('entity')
            ->andWhere('IDENTITY(entity.asset) = :assetId')
            ->setParameter('assetId', $assetId);
    }
}
