<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\PodcastFilter;

final class PodcastApiParams
{
    public static function applyCustomFilter(ApiParams $apiParams, ExtSystem $extSystem): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][PodcastFilter::EXT_SYSTEM] = $extSystem->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }

    public static function applyLicenceCustomFilter(ApiParams $apiParams, AssetLicence $assetLicence): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][PodcastFilter::LICENCE] = $assetLicence->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
