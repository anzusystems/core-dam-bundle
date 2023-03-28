<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomAssetStatusForAssetFileFilter;

final class AssetStatusForAssetFileApiParams
{
    public static function applyCustomFilter(ApiParams $apiParams, AssetStatus $status): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][CustomAssetStatusForAssetFileFilter::ASSET_STATUS] = $status->toString();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
