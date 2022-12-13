<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginExternalProvider;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @extends AbstractAnzuRepository<AssetFile>
 *
 * @method AssetFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetFile|null findOneBy(array $criteria, array $orderBy = null)
 */
final class AssetFileRepository extends AbstractAssetFileRepository
{
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

    protected function getEntityClass(): string
    {
        return AssetFile::class;
    }
}
