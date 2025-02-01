<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionManager;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\JwDistributionAdmUpdateDto;

final readonly class JwDistributionUpdateFacade
{
    public function __construct(
        private JwDistributionManager $jwDistributionManager,
        private JwDistributionFacade $jwDistributionFacade,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function upsert(JwDistributionAdmUpdateDto $dto): JwDistribution
    {
        $distribution = $dto->getDistribution();
        if (false === (null === $distribution) && false === ($distribution instanceof JwDistribution)) {
            throw (new ValidationException())
                ->addFormattedError('_resourceName', ValidationException::ERROR_FIELD_INVALID);
        }

        $distribution = null === $distribution
            ? $this->createJw($dto)
            : $this->updateJw($dto, $distribution)
        ;

        return $distribution;
    }

    private function createJw(JwDistributionAdmUpdateDto $dto): JwDistribution
    {
        $distribution = $this->jwDistributionFacade->preparePayload($dto->getAssetFile(), $dto->getDistributionService());
        $distribution = $this->setupCommon($dto, $distribution);
        $this->jwDistributionManager->create($distribution);

        return $distribution;
    }

    private function updateJw(JwDistributionAdmUpdateDto $dto, JwDistribution $distribution): JwDistribution
    {
        $distribution = $this->setupCommon($dto, $distribution);
        $this->jwDistributionManager->updateExisting($distribution);

        return $distribution;
    }

    private function setupCommon(JwDistributionAdmUpdateDto $dto, JwDistribution $distribution): JwDistribution
    {
        $distribution
            ->setExtId($dto->getExtId())
            ->setStatus($dto->getStatus())
            ->setDistributionService($dto->getDistributionService())
            ->setDirectSourceUrl($dto->getDirectSourceUrl())
        ;

        return $distribution;
    }
}
