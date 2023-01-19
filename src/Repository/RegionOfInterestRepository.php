<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @extends AbstractAnzuRepository<RegionOfInterest>
 *
 * @method RegionOfInterest|null find($id, $lockMode = null, $lockVersion = null)
 * @method RegionOfInterest|null findOneBy(array $criteria, array $orderBy = null)
 */
final class RegionOfInterestRepository extends AbstractAnzuRepository
{
    /**
     * @throws NonUniqueResultException
     */
    public function findByImageIdAndPosition(string $assetId, int $roiPosition): ?RegionOfInterest
    {
        return $this->createQueryBuilder('entity')
            ->innerJoin('entity.image', 'image')
            ->andWhere('image.id = :imageId')
            ->andWhere('entity.position = :roiPosition')
            ->setParameter('imageId', $assetId)
            ->setParameter('roiPosition', $roiPosition)
            ->setMaxResults(1) // todo tmp fix (multiple files at same position in slot)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findLastByImage(ImageFile $image): ?RegionOfInterest
    {
        return $this->createQueryBuilder('entity')
            ->where('IDENTITY(entity.image) = :imageId')
            ->orderBy('entity.position', Criteria::DESC)
            ->setParameter('imageId', $image->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    protected function getEntityClass(): string
    {
        return RegionOfInterest::class;
    }
}
