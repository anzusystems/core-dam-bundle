<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginExternalProvider;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginStorage;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @extends AbstractAssetFileRepository<AssetFile>
 *
 * @method AssetFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetFile|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AssetFileRepository extends AbstractAssetFileRepository
{
    private const int DEFAULT_FAILED_REASONS_LIMIT = 3;

    /**
     * @return Collection<array-key, AssetFile>
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
     * @throws NonUniqueResultException
     */
    public function findOneByOriginExternalProviderAndLicence(
        OriginExternalProvider $originExternalProvider,
        AssetLicence $assetLicence,
    ): ?AssetFile {
        return $this->createQueryBuilder('entity')
            ->where('IDENTITY(entity.licence) = :licenceId')
            ->andWhere('entity.assetAttributes.originExternalProvider = :originExternalProvider')
            ->setParameter('licenceId', $assetLicence->getId())
            ->setParameter('originExternalProvider', $originExternalProvider->toString())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<AssetFileFailedType>
     */
    public function findFailedReasonsByOriginStorage(
        OriginStorage $originStorage,
        int $limit = self::DEFAULT_FAILED_REASONS_LIMIT,
        ?DateTimeInterface $failedSince = null,
    ): array {
        $queryBuilder = $this->createQueryBuilder('entity')
            ->select('entity.assetAttributes.failReason AS failReason')
            ->where('entity.assetAttributes.originStorage = :originStorage')
            ->andWhere('entity.assetAttributes.status = :status')
            ->setParameter('originStorage', $originStorage->toString())
            ->setParameter('status', AssetFileProcessStatus::Failed->toString());
        if ($failedSince instanceof DateTimeInterface) {
            $queryBuilder
                ->andWhere('entity.createdAt >= :failedSince')
                ->setParameter('failedSince', $failedSince);
        }

        return array_column(
            $queryBuilder
                ->orderBy('entity.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getArrayResult(),
            'failReason',
        );
    }

    /**
     * @param int $maxFilesCount - is required as a regular count on mysql could walk through all file related rows,
     *                             this way we count only rows int the limit
     */
    public function getLimitedCountByAssetLicence(AssetLicence $licence, int $maxFilesCount): int
    {
        $results = $this->createQueryBuilder('entity')
            ->select('entity.id')
            ->where('IDENTITY(entity.licence) = :licenceId')
            ->setParameter('licenceId', $licence->getId())
            ->setMaxResults($maxFilesCount)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        return count($results);
    }

    /**
     * Existence probe over IDX_attributes_taken_over_from: tells whether the given file is already the root of
     * a take-over group of its own.
     */
    public function existsTakenOverFrom(string $takenOverFromId): bool
    {
        return [] !== $this->createQueryBuilder('entity')
            ->select('entity.id')
            ->where('entity.assetAttributes.takenOverFromId = :takenOverFromId')
            ->setParameter('takenOverFromId', $takenOverFromId)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleColumnResult()
        ;
    }

    /**
     * Every file of the given take-over groups: each root itself plus every copy pointing at it. A root
     * already deleted by retention simply contributes no row of its own, its copies still come back.
     *
     * @param string[] $rootIds
     *
     * @return list<AssetFile>
     */
    public function findGroupFiles(array $rootIds, bool $lock = false): array
    {
        if ([] === $rootIds) {
            return [];
        }

        $query = $this->createQueryBuilder('entity')
            ->where('entity.id in (:rootIds)')
            ->orWhere('entity.assetAttributes.takenOverFromId in (:rootIds)')
            ->setParameter('rootIds', $rootIds)
            ->getQuery()
        ;
        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        /** @var list<AssetFile> $files */
        $files = $query->getResult();

        return $files;
    }

    /**
     * Single use files whose claim is due for a usage check, oldest id first; rides
     * IDX_attributes_used_by_check_after. The id cursor is what makes a run resumable: a file the caller
     * decides to leave alone keeps its due date and would otherwise come back in the very next page.
     *
     * @return list<AssetFile>
     */
    public function findUsageChecksDue(DateTimeInterface $checkAfter, int $limit, string $idFrom): array
    {
        /** @var list<AssetFile> $files */
        $files = $this->createQueryBuilder('entity')
            ->where('entity.assetAttributes.usedByCheckAfter <= :checkAfter')
            ->andWhere('entity.id > :idFrom')
            ->setParameter('checkAfter', $checkAfter)
            ->setParameter('idFrom', $idFrom)
            ->orderBy('entity.id', App::ORDER_ASC)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $files;
    }

    /**
     * Everything the given holder currently holds; rides IDX_attributes_used_by_holder.
     *
     * @return list<AssetFile>
     */
    public function findByHolder(string $holderName, string $holderId, bool $lock = false): array
    {
        $query = $this->createQueryBuilder('entity')
            ->where('entity.assetAttributes.usedByHolderName = :holderName')
            ->andWhere('entity.assetAttributes.usedByHolderId = :holderId')
            ->setParameter('holderName', $holderName)
            ->setParameter('holderId', $holderId)
            ->getQuery()
        ;
        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        /** @var list<AssetFile> $files */
        $files = $query->getResult();

        return $files;
    }

    protected function getEntityClass(): string
    {
        return AssetFile::class;
    }
}
