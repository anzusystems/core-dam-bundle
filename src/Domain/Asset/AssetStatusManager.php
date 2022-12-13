<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;

final class AssetStatusManager extends AssetManager
{
    public function toDeleting(Asset $asset): Asset
    {
        $asset->getAttributes()
            ->setStatus(AssetStatus::Deleting);

        return $this->updateExisting($asset);
    }
}
