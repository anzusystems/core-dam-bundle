<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class LicensedEntityFilter implements CustomFilterInterface
{
    use AssetLicenceExtSystemFilterTrait;

    public const string EXT_SYSTEM = 'extSystem';
    public const string LICENCE = 'licence';
    public const string ASSET_LICENCE = 'assetLicence';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::EXT_SYSTEM === $field) {
            $dqb
                ->innerJoin('t.licence', 'licence')
                ->andWhere('IDENTITY(licence.extSystem) = :extSystemId')
                ->setParameter('extSystemId', $value);
        }
        if (self::LICENCE === $field) {
            $dqb
                ->andWhere('IDENTITY(t.licence) = :licenceId')
                ->setParameter('licenceId', $value);
        }
        // Scope by the ext system the given asset licence belongs to: returns every licensed entity that
        // shares an ext system with the input licence (not just the same licence).
        if (self::ASSET_LICENCE === $field) {
            $dqb->innerJoin('t.licence', 'licence');
            $this->applyAssetLicenceExtSystemFilter($dqb, 'IDENTITY(licence.extSystem)', $value);
        }

        return $dqb;
    }
}
