<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

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

    protected function getEntityClass(): string
    {
        return Asset::class;
    }
}
