<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\JwDistribution;

use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionBodyBuilder;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use Doctrine\ORM\NonUniqueResultException;

class JwDistributionFacade
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
        $this->distributionBodyBuilder->setBaseFields($distributionService, $distribution);
        $this->distributionBodyBuilder->setProperties($distributionService, $assetFile, $distribution->getTexts());
        $distribution->getTexts()->setKeywords($this->distributionBodyBuilder->getKeywords($assetFile));
        $distribution->getTexts()->setAuthor($this->distributionBodyBuilder->getFirstAuthor($assetFile));

        return $distribution;
    }
}
