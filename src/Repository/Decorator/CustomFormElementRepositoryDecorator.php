<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\CustomFormElementsApiParams;
use AnzuSystems\CoreDamBundle\Entity\CustomForm;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomFormElementsFilter;
use AnzuSystems\CoreDamBundle\Repository\CustomFormElementRepository;
use Doctrine\ORM\Exception\ORMException;

final class CustomFormElementRepositoryDecorator
{
    public function __construct(
        private readonly CustomFormElementRepository $customFormElementRepository,
    ) {
    }

    /**
     * @throws ORMException
     */
    public function findByApiParams(ApiParams $apiParams, CustomForm $form): ApiResponseList
    {
        return $this->customFormElementRepository->findByApiParams(
            apiParams: CustomFormElementsApiParams::applyCustomFilter($apiParams, $form),
            customFilters: [new CustomFormElementsFilter()],
        );
    }
}
