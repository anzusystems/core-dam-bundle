<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @extends AbstractAnzuRepository<ExtSystem>
 *
 * @method ExtSystem|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExtSystem|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method ExtSystem|null findProcessedById(string $id)
 * @method ExtSystem|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class ExtSystemRepository extends AbstractAnzuRepository
{
    public function findOneBySlug(string $slug): ?ExtSystem
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @param array<int, string> $slugs
     *
     * @return Collection<int, ExtSystem>
     */
    public function findAllExcept(array $slugs): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('entity')
                ->where('entity.slug not in (:slugs)')
                ->setParameter('slugs', $slugs)
                ->getQuery()
                ->getResult()
        );
    }

    protected function getEntityClass(): string
    {
        return ExtSystem::class;
    }
}
