<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\ResourceCustomForm;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @extends AbstractAnzuRepository<ResourceCustomForm>
 *
 * @method ResourceCustomForm|null find($id, $lockMode = null, $lockVersion = null)
 * @method ResourceCustomForm|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method ResourceCustomForm|null findProcessedById(string $id)
 * @method ResourceCustomForm|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class ResourceCustomFormRepository extends AbstractAnzuRepository
{
    /**
     * @throws NonUniqueResultException
     */
    public function findByResource(string $resource): ?ResourceCustomForm
    {
        return $this->createQueryBuilder('entity')
            ->andWhere('entity.resourceKey = :resourceKey')
            ->setParameter('resourceKey', $resource)
            ->getQuery()
            ->getOneOrNullResult();
    }

    protected function getEntityClass(): string
    {
        return ResourceCustomForm::class;
    }
}
