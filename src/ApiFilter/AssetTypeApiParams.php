<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomAssetTypeFilter;

final class AssetTypeApiParams
{
    public static function applyCustomFilter(ApiParams $apiParams, AssetType $type): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][CustomAssetTypeFilter::ASSET_TYPE] = $type->toString();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
