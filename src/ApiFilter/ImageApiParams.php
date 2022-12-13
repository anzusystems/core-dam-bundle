<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomImageFilter;

final class ImageApiParams
{
    public static function applyCustomFilter(ApiParams $apiParams, ImageFile $imageFile): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][CustomImageFilter::IMAGE] = $imageFile->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
