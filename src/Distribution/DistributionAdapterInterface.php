<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Dto\CustomDistribution\CustomDistributionAdmDto;

interface DistributionAdapterInterface
{
    public function decorateDistribution(Distribution $distribution): CustomDistributionAdmDto;

    public function preparePayload(AssetFile $assetFile, string $distributionService): Distribution;

    public function createDistributionEntity(AssetFile $assetFile, CustomDistributionAdmDto $distributionDto): Distribution;
}
