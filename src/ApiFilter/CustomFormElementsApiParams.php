<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\ApiFilter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\Entity\CustomForm;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomFormElementsFilter;

final class CustomFormElementsApiParams
{
    public static function applyCustomFilter(ApiParams $apiParams, CustomForm $form): ApiParams
    {
        $filter = $apiParams->getFilter();
        $filter[ApiParams::FILTER_CUSTOM][CustomFormElementsFilter::FORM] = $form->getId();
        $apiParams->setFilter($filter);

        return $apiParams;
    }
}
