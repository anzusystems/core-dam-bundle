<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomDistribution;

use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionBodyBuilder;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\CustomDistribution;
use Doctrine\ORM\NonUniqueResultException;

class CustomDistributionFacade
{
    public function __construct(
        private readonly DistributionBodyBuilder $distributionBodyBuilder,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    public function preparePayload(AssetFile $assetFile, string $distributionService): CustomDistribution
    {
        $distribution = new CustomDistribution();
        $distribution->setDistributionService($distributionService);

        $this->distributionBodyBuilder->setBaseFields($distributionService, $distribution);
        $this->distributionBodyBuilder->setWriterProperties(
            $distributionService,
            $assetFile->getAsset(),
            $distribution
        );

        return $distribution;
    }
}
