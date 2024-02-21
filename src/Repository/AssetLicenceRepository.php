<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @extends AbstractAnzuRepository<AssetLicence>
 *
 * @method AssetLicence|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetLicence|null findOneBy($id, $lockMode = null, $lockVersion = null)
 * @method AssetLicence|null findProcessedById(string $id)
 * @method AssetLicence|null findProcessedByIdAndFilename(string $id, string $slug)
 */
final class AssetLicenceRepository extends AbstractAnzuRepository
{
    public function findOneByExtSystemAndExtId(ExtSystem $extSystem, string $extId): ?AssetLicence
    {
        return $this->findOneBy([
            'extSystem' => $extSystem,
            'extId' => $extId,
        ]);
    }

    public function findByIds(array $ids): Collection
    {
        return new ArrayCollection(
            $this->findBy([
                'id' => $ids,
            ])
        );
    }

    protected function getEntityClass(): string
    {
        return AssetLicence::class;
    }
}
