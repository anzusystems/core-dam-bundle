<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

interface AssetFileAdmCreateDtoInterface
{
    public function getMimeType(): string;

    public function getAssetType(): AssetType;

    public function getChecksum(): string;
}
