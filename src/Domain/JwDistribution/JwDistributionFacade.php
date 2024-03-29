<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\JwDistribution;

use AnzuSystems\CoreDamBundle\Domain\Distribution\AbstractDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionBodyBuilder;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use Doctrine\ORM\NonUniqueResultException;

class JwDistributionFacade extends AbstractDistributionFacade
{
    public function __construct(
        private readonly DistributionBodyBuilder $distributionBodyBuilder,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    public function preparePayload(AssetFile $assetFile, string $distributionService): JwDistribution
    {
        $distribution = new JwDistribution();
        $this->distributionBodyBuilder->setBaseFields($assetFile, $distributionService, $distribution);

        $this->distributionBodyBuilder->setWriterProperties(
            $distributionService,
            $assetFile->getAsset(),
            $distribution
        );

        return $distribution;
    }
}
