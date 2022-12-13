<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @extends AbstractAnzuRepository<CustomFormElement>
 *
 * @method CustomFormElement|null find($id, $lockMode = null, $lockVersion = null)
 * @method CustomFormElement|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method CustomFormElement|null findProcessedById(string $id)
 * @method CustomFormElement|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class CustomFormElementRepository extends AbstractAnzuRepository
{
    public function findAllAssetSearchableElementsByForms(array $ids): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->innerJoin('entity.form', 'form')
                ->where('entity.attributes.searchable = :true')
                ->andWhere('IDENTITY(entity.form) in (:formIds)')
                ->setParameter('true', true)
                ->setParameter('formIds', $ids)
                ->getQuery()
                ->getResult()
        );
    }

    protected function getEntityClass(): string
    {
        return CustomFormElement::class;
    }
}
