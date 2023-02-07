<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\VideoShowEpisodeFilter;

final class VideoShowEpisodeApiParams
{
    public static function applyCustomFilter(ApiParams $apiParams, VideoShow $videoShow): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][VideoShowEpisodeFilter::VIDEO_SHOW] = $videoShow->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }

    public static function applyCustomFilterByAsset(ApiParams $apiParams, Asset $asset): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][VideoShowEpisodeFilter::ASSET] = $asset->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
