<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\AssetHasFile\AssetHasFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetHasFile\AssetHasFileManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @template-covariant T of AssetFile
 */
final class AssetFilePositionFacade
{
    private AssetHasFileFactory $assetHasFileFactory;
    private AssetHasFileManager $assetHasFileManager;

    #[Required]
    public function setAssetHasFileFactory(AssetHasFileFactory $assetHasFileFactory): void
    {
        $this->assetHasFileFactory = $assetHasFileFactory;
    }

    #[Required]
    public function setAssetHasFileManager(AssetHasFileManager $assetHasFileManager): void
    {
        $this->assetHasFileManager = $assetHasFileManager;
    }

    public function setToPosition(Asset $asset, AssetFile $assetFile, string $version): AssetFile
    {
        // validation
        // todo special lock
        // todo validate same licence
        //

        $this->assetHasFileManager->delete($assetFile->getAsset(), false);
        // todo update asset default/main/attrs/...
        $this->assetHasFileFactory->createRelation($asset, $assetFile, $version);

        return $assetFile;
    }
}
