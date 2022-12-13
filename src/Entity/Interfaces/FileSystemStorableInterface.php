<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

interface FileSystemStorableInterface
{
    public function getFilePath(): string;

    public function getAssetType(): AssetType;

    public function getExtSystem(): ExtSystem;

    //    public function getAssetType(): AssetType;
}
