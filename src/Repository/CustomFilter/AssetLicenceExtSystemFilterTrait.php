<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use Doctrine\ORM\QueryBuilder;

/**
 * Shared "scope by the ext system the given asset licence belongs to" custom-filter logic. Both the
 * licensed-entity catalog ({@see LicensedEntityFilter}) and the directly ext-system-bound catalog
 * ({@see CustomExtSystemFilter}) need it; the only difference is which DQL expression resolves the
 * candidate row's ext system.
 */
trait AssetLicenceExtSystemFilterTrait
{
    /**
     * @param string $extSystemIdentityExpr DQL expression for the candidate row's ext system id,
     *                                       e.g. `IDENTITY(t.extSystem)` or `IDENTITY(licence.extSystem)`
     */
    private function applyAssetLicenceExtSystemFilter(
        QueryBuilder $dqb,
        string $extSystemIdentityExpr,
        string | int $assetLicenceId,
    ): void {
        $dqb
            ->andWhere(sprintf(
                '%s = (SELECT IDENTITY(filterLicence.extSystem) FROM %s filterLicence WHERE filterLicence.id = :assetLicenceId)',
                $extSystemIdentityExpr,
                AssetLicence::class
            ))
            ->setParameter('assetLicenceId', $assetLicenceId);
    }
}
