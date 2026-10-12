<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionFacade;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use Doctrine\ORM\NonUniqueResultException;

final class VideoDistributionStarter
{
    public function __construct(
        private readonly JwDistributionFacade $jwDistributionFacade,
        private readonly DistributionRepository $distributionRepository,
        private readonly DistributionBroker $distributionBroker,
    ) {
    }

    /**
     * @param list<string> $distributionServices
     *
     * @throws NonUniqueResultException
     */
    public function start(VideoFile $videoFile, array $distributionServices): void
    {
        foreach ($distributionServices as $distributionService) {
            $existing = $this->distributionRepository->findByAssetFileAndDistributionService(
                (string) $videoFile->getId(),
                $distributionService
            );
            if (null !== $existing) {
                if ($existing->getStatus()->is(DistributionProcessStatus::Waiting)) {
                    $this->distributionBroker->startDistribution($existing);
                }

                continue;
            }

            $this->jwDistributionFacade->distribute(
                $videoFile,
                $this->jwDistributionFacade->preparePayload($videoFile, $distributionService)
            );
        }
    }
}
