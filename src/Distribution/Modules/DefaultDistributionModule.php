<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionModule;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

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
