<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\PublicExport;

/**
 * @extends AbstractAnzuRepository<PublicExport>
 *
 * @method PublicExport|null find($id, $lockMode = null, $lockVersion = null)
 * @method PublicExport|null findOneBy(array $criteria, array $orderBy = null)
 */
final class PublicExportRepository extends AbstractAnzuRepository
{
    public function findOneBySlug(string $slug): ?PublicExport
    {
        return $this->findOneBy([
            'slug' => $slug,
        ]);
    }

    protected function getEntityClass(): string
    {
        return PublicExport::class;
    }
}
