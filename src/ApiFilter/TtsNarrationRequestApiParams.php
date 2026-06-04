<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\TtsNarrationRequestExtSystemFilter;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\TtsNarrationRequestLicenceFilter;

final class TtsNarrationRequestApiParams
{
    public static function applyLicenceCustomFilter(ApiParams $apiParams, AssetLicence $assetLicence): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][TtsNarrationRequestLicenceFilter::LICENCE] = (string) $assetLicence->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }

    public static function applyExtSystemCustomFilter(ApiParams $apiParams, ExtSystem $extSystem): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][TtsNarrationRequestExtSystemFilter::EXT_SYSTEM] = (string) $extSystem->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
