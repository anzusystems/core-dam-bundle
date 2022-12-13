<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionModule;
use AnzuSystems\CoreDamBundle\Distribution\RemoteProcessingDistributionModuleInterface;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Exception\DistributionFailedException;
use AnzuSystems\CoreDamBundle\Model\Configuration\DistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionFailReason;

final class MockDistributionModule extends AbstractDistributionModule implements RemoteProcessingDistributionModuleInterface
{
    public function distribute(Distribution $distribution): void
    {
        $assetFile = $this->assetFileRepository->find($distribution->getAssetFileId());
        if (null === $assetFile) {
            return;
        }

        $config = $this->distributionConfigurationProvider->getDistributionService($distribution->getDistributionService());
        $distribution->setExtId($config->getMockOptions()->getExtId());
        $this->sleep($config);

        if ($config->getMockOptions()->getFailReason()->isNot(DistributionFailReason::None)) {
            throw new DistributionFailedException($config->getMockOptions()->getFailReason());
        }
    }

    public function checkDistributionStatus(Distribution $distribution): void
    {
        $config = $this->distributionConfigurationProvider->getDistributionService($distribution->getDistributionService());
        $distribution->setDistributionData($config->getMockOptions()->getDistributionData());
        $this->sleep($config);
    }

    public function supportsAssetType(): array
    {
        return [
            AssetType::Video,
        ];
    }

    private function sleep(DistributionServiceConfiguration $configuration): void
    {
        if ($configuration->getMockOptions()->getSleep() > 0) {
            sleep($configuration->getMockOptions()->getSleep());
        }
    }
}
