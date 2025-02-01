<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionManager;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\CustomDistributionAdmUpdateDto;

final readonly class CustomDistributionUpdateFacade
{
    public function __construct(
        private JwDistributionManager $jwDistributionManager,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function upsert(CustomDistributionAdmUpdateDto $dto, Distribution $distribution): Distribution
    {
        $distribution
            ->setExtId($dto->getExtId())
            ->setStatus($dto->getStatus())
            ->setDistributionService($dto->getDistributionService())
        ;
        $this->jwDistributionManager->updateExisting($distribution);

        return $distribution;
    }
}
