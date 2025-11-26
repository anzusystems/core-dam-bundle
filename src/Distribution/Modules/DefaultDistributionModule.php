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

final class DefaultDistributionModule extends AbstractDistributionModule
{
    public function distribute(Distribution $distribution): void
    {

    }

    public function checkDistributionStatus(Distribution $distribution): void
    {
    }

    public function supportsAssetType(): array
    {
        return [
            AssetType::Video,
            AssetType::Audio,
        ];
    }
}
