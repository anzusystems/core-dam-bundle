<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomExtSystemFilter;

final class ExtSystemApiParams
{
    public static function applyCustomFilter(ApiParams $apiParams, ExtSystem $extSystem): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][CustomExtSystemFilter::EXT_SYSTEM] = $extSystem->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }

    /**
     * Scopes an ext-system-bound catalog by the ext system the given asset licence belongs to. The licence
     * already carries its ext system, so resolve it here and reuse the plain ext-system filter.
     */
    public static function applyAssetLicenceExtSystemCustomFilter(ApiParams $apiParams, AssetLicence $assetLicence): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][CustomExtSystemFilter::EXT_SYSTEM] = $assetLicence->getExtSystem()->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
