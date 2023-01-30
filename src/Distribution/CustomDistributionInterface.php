<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\CustomDistribution;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Dto\CustomDistribution\CustomDistributionAdmDto;

interface CustomDistributionInterface
{
    public function createFromCustomDistributionDto(AssetFile $assetFile, CustomDistributionAdmDto $distribution): Distribution;

    public function addPayload(AssetFile $assetFile, CustomDistribution $distribution): CustomDistribution;
}
