<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetHasFile;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetHasFile;

class AssetHasFileManager extends AbstractManager
{
    public function create(AssetHasFile $assetHasFile, bool $flush = true): AssetHasFile
    {
        $this->trackCreation($assetHasFile);
        $this->entityManager->persist($assetHasFile);
        $this->flush($flush);

        return $assetHasFile;
    }

    public function delete(AssetHasFile $assetHasFile, bool $flush = true): bool
    {
        $assetHasFile->getAsset()->getFiles()->removeElement($assetHasFile);
        $assetHasFile->getAssetFile()->getSlots()->removeElement($assetHasFile);
        $this->entityManager->remove($assetHasFile);
        $this->flush($flush);

        return true;
    }
}
