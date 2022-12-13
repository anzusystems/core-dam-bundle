<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileMetadata;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFileMetadata;

final class AssetFileMetadataManager extends AbstractManager
{
    public function create(AssetFileMetadata $assetFileMetadata, bool $flush = true): AssetFileMetadata
    {
        $this->trackCreation($assetFileMetadata);
        $this->entityManager->persist($assetFileMetadata);
        $this->flush($flush);

        return $assetFileMetadata;
    }
}
