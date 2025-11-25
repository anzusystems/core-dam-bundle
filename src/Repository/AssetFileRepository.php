<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginExternalProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @extends AbstractAssetFileRepository<AssetFile>
 *
 * @method AssetFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetFile|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AssetFileRepository extends AbstractAssetFileRepository
{
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

    protected function getEntityClass(): string
    {
        return AssetFile::class;
    }
}
