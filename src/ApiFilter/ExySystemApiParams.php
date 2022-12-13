<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomExtSystemFilter;

final class ExySystemApiParams
{
    public static function applyCustomFilter(ApiParams $apiParams, ExtSystem $extSystem): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][CustomExtSystemFilter::EXT_SYSTEM] = $extSystem->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
