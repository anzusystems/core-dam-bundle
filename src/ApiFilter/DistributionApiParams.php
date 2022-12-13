<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomDistributionFilter;

final class DistributionApiParams
{
    public static function applyAssetFileCustomFilter(ApiParams $apiParams, AssetFile $assetFile): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][CustomDistributionFilter::ASSET_FILE_ID] = $assetFile->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }

    public static function applyAssetCustomFilter(ApiParams $apiParams, Asset $asset): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][CustomDistributionFilter::ASSET_ID] = $asset->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
