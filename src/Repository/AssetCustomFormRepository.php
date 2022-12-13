<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetCustomForm;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @extends AbstractAnzuRepository<AssetCustomForm>
 *
 * @method AssetCustomForm|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetCustomForm|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method AssetCustomForm|null findProcessedById(string $id)
 * @method AssetCustomForm|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class AssetCustomFormRepository extends AbstractAnzuRepository
{
    /**
     * @throws NonUniqueResultException
     */
    public function findOneByTypeAndExtSystem(ExtSystem $extSystem, AssetType $assetType): ?AssetCustomForm
    {
        return $this->createQueryBuilder('entity')
            ->andWhere('IDENTITY(entity.extSystem) = :extSystemId')
            ->andWhere('entity.assetType = :assetType')
            ->setParameter('extSystemId', (int) $extSystem->getId())
            ->setParameter('assetType', $assetType->toString())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllByExtSystemSlug(string $extSystemSlug): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->innerJoin('entity.extSystem', 'extSystem')
                ->andWhere('extSystem.slug = :extSystemSlug')
                ->setParameter('extSystemSlug', $extSystemSlug)
                ->getQuery()
                ->getResult()
        );
    }

    protected function getEntityClass(): string
    {
        return AssetCustomForm::class;
    }
}
