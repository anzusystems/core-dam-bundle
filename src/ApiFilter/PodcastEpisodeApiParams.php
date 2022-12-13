<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\PodcastEpisodeFilter;

final class PodcastEpisodeApiParams
{
    public static function applyCustomFilter(ApiParams $apiParams, Podcast $podcast): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][PodcastEpisodeFilter::PODCAST] = $podcast->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }

    public static function applyCustomFilterByAsset(ApiParams $apiParams, Asset $asset): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][PodcastEpisodeFilter::ASSET] = $asset->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
