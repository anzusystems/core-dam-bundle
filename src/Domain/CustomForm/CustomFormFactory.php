<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomForm;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetCustomForm;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ResourceCustomForm;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final class CustomFormFactory extends AbstractManager
{
    public function createAssetCustomForm(AssetType $assetType, ExtSystem $extSystem): AssetCustomForm
    {
        return (new AssetCustomForm())
            ->setAssetType($assetType)
            ->setExtSystem($extSystem);
    }

    public function createDistributionServiceCustomForm(string $distributionService): ResourceCustomForm
    {
        return (new ResourceCustomForm())
            ->setResourceKey($this->getDistributionServiceResourceKey($distributionService));
    }

    public static function getDistributionServiceResourceKey(string $distributionService): string
    {
        return sprintf('distribution_service_%s', $distributionService);
    }
}
