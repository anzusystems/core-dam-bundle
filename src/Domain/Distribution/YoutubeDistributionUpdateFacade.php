<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\YoutubeDistribution\YoutubeAbstractDistributionManager;
use AnzuSystems\CoreDamBundle\Domain\YoutubeDistribution\YoutubeDistributionFacade;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\YoutubeDistributionAdmUpdateDto;

final readonly class YoutubeDistributionUpdateFacade
{
    public function __construct(
        private YoutubeAbstractDistributionManager $youtubeManager,
        private YoutubeDistributionFacade $youtubeDistributionFacade,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function upsert(YoutubeDistributionAdmUpdateDto $dto): YoutubeDistribution
    {
        $distribution = $dto->getDistribution();
        if (false === (null === $distribution) && false === ($distribution instanceof YoutubeDistribution)) {
            throw (new ValidationException())
                ->addFormattedError('_resourceName', ValidationException::ERROR_FIELD_INVALID);
        }

        $distribution = null === $distribution
            ? $this->createYoutube($dto)
            : $this->updateYoutube($dto, $distribution)
        ;

        return $distribution;
    }

    private function updateYoutube(YoutubeDistributionAdmUpdateDto $dto, YoutubeDistribution $distribution): YoutubeDistribution
    {
        $distribution = $this->setupCommon($dto, $distribution);
        $this->youtubeManager->updateExisting($distribution);

        return $distribution;
    }

    private function createYoutube(YoutubeDistributionAdmUpdateDto $dto): YoutubeDistribution
    {
        $distribution = $this->youtubeDistributionFacade->preparePayload($dto->getAssetFile(), $dto->getDistributionService());
        $distribution = $this->setupCommon($dto, $distribution);
        $this->youtubeManager->create($distribution);

        return $distribution;
    }

    private function setupCommon(YoutubeDistributionAdmUpdateDto $dto, YoutubeDistribution $distribution): YoutubeDistribution
    {
        $distribution
            ->setExtId($dto->getExtId())
            ->setStatus($dto->getStatus())
            ->setDistributionService($dto->getDistributionService())
        ;

        return $distribution;
    }
}
