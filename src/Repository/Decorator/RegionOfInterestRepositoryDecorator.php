<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CoreDamBundle\ApiFilter\ImageApiParams;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Model\Dto\RegionOfInterest\RegionOfInterestAdmListDto;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomImageFilter;
use AnzuSystems\CoreDamBundle\Repository\RegionOfInterestRepository;
use Doctrine\ORM\Exception\ORMException;

final class RegionOfInterestRepositoryDecorator
{
    public function __construct(
        private readonly RegionOfInterestRepository $regionOfInterestRepository,
    ) {
    }

    /**
     * @throws ORMException
     */
    public function findByApiParamsWithInfiniteListing(ApiParams $apiParams, ImageFile $imageFile): ApiInfiniteResponseList
    {
        $list = $this->regionOfInterestRepository->findByApiParamsWithInfiniteListing(
            apiParams: ImageApiParams::applyCustomFilter($apiParams, $imageFile),
            customFilters: [new CustomImageFilter()],
        );

        return $list->setData(
            array_map(
                fn (RegionOfInterest $roi): RegionOfInterestAdmListDto => RegionOfInterestAdmListDto::getInstance($roi),
                $list->getData()
            )
        );
    }
}
