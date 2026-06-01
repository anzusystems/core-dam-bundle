<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\CustomFilter;

use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\ORM\QueryBuilder;

final class CustomExtSystemFilter implements CustomFilterInterface
{
    use AssetLicenceExtSystemFilterTrait;

    public const string EXT_SYSTEM = 'extSystem';
    public const string ASSET_LICENCE = 'assetLicence';

    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder
    {
        if (self::EXT_SYSTEM === $field) {
            $dqb->andWhere('IDENTITY(t.extSystem) = :extSystemId')
                ->setParameter('extSystemId', $value);
        }
        // Scope by the ext system the given asset licence belongs to: lists every entity sharing that
        // ext system. Used to filter ext-system-bound catalogs (e.g. voice families) by a CMS asset licence.
        if (self::ASSET_LICENCE === $field) {
            $this->applyAssetLicenceExtSystemFilter($dqb, 'IDENTITY(t.extSystem)', $value);
        }

        return $dqb;
    }
}
