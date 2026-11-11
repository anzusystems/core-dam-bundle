<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;

/**
 * The one place deciding which asset files must be single use; today the rule is the licence
 * flag, further rules (e.g. by file name) belong here as well.
 */
final readonly class AssetFileSingleUseEnforcer
{
    public function enforce(AssetFile $assetFile): bool
    {
        if ($assetFile->getFlags()->isSingleUse() || false === $this->mustBeSingleUse($assetFile)) {
            return false;
        }

        $assetFile->getFlags()->setSingleUse(true);

        return true;
    }

    private function mustBeSingleUse(AssetFile $assetFile): bool
    {
        return $assetFile->getLicence()->getFlags()->isSingleUseEnforced();
    }
}
