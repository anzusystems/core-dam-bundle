<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Decorator;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\ApiFilter\DistributionApiParams;
use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Dto\CustomDistribution\CustomDistributionAdmDto;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomDistributionFilter;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use Doctrine\ORM\Exception\ORMException;

final readonly class DistributionRepositoryDecorator
{
    public function __construct(
        private DistributionRepository $distributionRepository,
        private ModuleProvider $moduleProvider,
    ) {
    }

    public function decorate(Distribution $distribution): Distribution|CustomDistributionAdmDto
    {
        $adapter = $this->moduleProvider->provideAdapter($distribution->getDistributionService());
        if ($adapter) {
            return $adapter->decorateDistribution($distribution);
        }

        return $distribution;
    }

    /**
     * @return ApiResponseList<Distribution|CustomDistributionAdmDto>
     *
     * @throws ORMException
     */
    public function findByApiParamsByAssetFile(ApiParams $apiParams, AssetFile $assetFile): ApiResponseList
    {
        /** @var ApiResponseList<Distribution> $responseList */
        $responseList = $this->distributionRepository->findByApiParamsByAssetFile($apiParams, $assetFile);

        return $responseList->setData(
            $this->mapToDecorators($responseList->getData())
        );
    }

    /**
     * @return ApiResponseList<Distribution|CustomDistributionAdmDto>
     *
     * @throws ORMException
     */
    public function findByApiParamsByAsset(ApiParams $apiParams, Asset $asset): ApiResponseList
    {
        /** @var ApiResponseList<Distribution> $responseList */
        $responseList = $this->distributionRepository->findByApiParams(
            apiParams: DistributionApiParams::applyAssetCustomFilter($apiParams, $asset),
            customFilters: [new CustomDistributionFilter()]
        );

        return $responseList->setData(
            $this->mapToDecorators($responseList->getData())
        );
    }

    /**
     * @template TKey of array-key
     *
     * @param array<TKey, Distribution> $data
     */
    private function mapToDecorators(array $data): array
    {
        return array_map(
            function (Distribution $distribution): Distribution|CustomDistributionAdmDto {
                return $this->decorate($distribution);
            },
            $data
        );
    }
}
