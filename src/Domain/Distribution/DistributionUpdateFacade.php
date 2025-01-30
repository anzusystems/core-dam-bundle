<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Distribution\Modules\YoutubeDistributionModule;
use AnzuSystems\CoreDamBundle\Domain\YoutubeDistribution\YoutubeAbstractDistributionManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Model\Decorator\DistributionServiceAuthorization;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\DistributionUpdateCollection;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\JwDistributionAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\YoutubeDistributionAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final readonly class DistributionUpdateFacade
{
    public function __construct(
        private DistributionRepository $distributionRepository,
        private YoutubeAbstractDistributionManager $youtubeManager,
        private ModuleProvider $moduleProvider,
    ) {
    }

    public function update(Asset $asset, DistributionUpdateCollection $updateCollection): DistributionUpdateCollection
    {
        $oldDistributions = $this->distributionRepository->findByAsset((string) $asset->getId());

        foreach ($updateCollection->getDistributions() as $distributionUpdateDto) {
            $existingDistribution = $oldDistributions->findFirst(
                fn (int $key, Distribution $distribution): bool => $distribution->getDistributionService() === $distributionUpdateDto->getDistributionService()
            );

            if (null === $existingDistribution) {
                continue;
            }

            if ($existingDistribution instanceof YoutubeDistribution && $distributionUpdateDto instanceof YoutubeDistributionAdmUpdateDto) {
                $this->updateYoutube($existingDistribution, $distributionUpdateDto);

                continue;
            }

            if ($existingDistribution instanceof JwDistribution && $distributionUpdateDto instanceof JwDistributionAdmUpdateDto) {
                $this->updateJw($existingDistribution, $distributionUpdateDto);

                continue;
            }

            // todo check access to distrib service

            dump($existingDistribution);
        }

        dump($oldDistributions->toArray());
        dump($updateCollection);

        return $updateCollection;
    }

    private function updateYoutube(YoutubeDistribution $distribution, YoutubeDistributionAdmUpdateDto $dto): YoutubeDistribution
    {
        $distribution
            ->setExtId($dto->getExtId())
            ->setStatus($dto->getStatus())
            ->setDistributionService($dto->getDistributionService())
        ;

        // todo update custom data

        return $distribution;
    }

    private function updateJw(JwDistribution $distribution, JwDistributionAdmUpdateDto $dto): JwDistribution
    {
        $distribution
            ->setExtId($dto->getExtId())
            ->setStatus($dto->getStatus())
            ->setDistributionService($dto->getDistributionService())
            ->setDirectSourceUrl($dto->getDirectSourceUrl())
        ;

        // todo update custom data

        return $distribution;
    }
}
