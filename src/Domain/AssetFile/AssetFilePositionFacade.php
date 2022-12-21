<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\AssetHasFile\AssetHasFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetHasFile\AssetHasFileManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @template-covariant T of AssetFile
 */
final class AssetFilePositionFacade
{
    private AssetHasFileFactory $assetHasFileFactory;
    private AssetHasFileManager $assetHasFileManager;
    private AssetManager $assetManager;
    private ExtSystemConfigurationProvider $extSystemConfigurationProvider;

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

    #[Required]
    public function setAssetManager(AssetManager $assetManager): void
    {
        $this->assetManager = $assetManager;
    }

    #[Required]
    public function setExtSystemConfigurationProvider(ExtSystemConfigurationProvider $extSystemConfigurationProvider): void
    {
        $this->extSystemConfigurationProvider = $extSystemConfigurationProvider;
    }

    public function setToPosition(Asset $asset, AssetFile $assetFile, string $version): AssetFile
    {
        $this->validateSlot($asset, $version);
        $this->validate($asset, $assetFile);

        $originAsset = $assetFile->getAsset();

        $this->removeOtherAssetSlots($asset, $assetFile);
        $this->assetHasFileFactory->createRelation($asset, $assetFile, $version, false);
        $assetFile->setAsset($asset);

        if (false === ($asset === $originAsset)) {
            $this->assetManager->updateExisting($originAsset, false);
        }

        $this->assetManager->updateExisting($asset);

        return $assetFile;
    }

    private function removeOtherAssetSlots(Asset $asset, AssetFile $assetFile): void
    {
        foreach ($assetFile->getSlots() as $slot) {
            if ($slot->getAsset() === $asset) {
                continue;
            }

            $this->assetHasFileManager->delete($slot, false);
        }
    }

    private function validate(Asset $asset, AssetFile $assetFile): void
    {
        if (false === ($asset->getAttributes()->getAssetType() === $assetFile->getAssetType())) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_ASSET_TYPE);
        }

        if (false === ($asset->getAttributes()->getAssetType() === $assetFile->getAssetType())) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_ASSET_TYPE);
        }

        if (false === ($asset->getLicence() === $assetFile->getLicence())) {
            throw new ForbiddenOperationException(ForbiddenOperationException::LICENCE_MISMATCH);
        }

        // todo fix same file on multiple positions
        if (1 === $assetFile->getAsset()->getFiles()->count()) {
            throw new ForbiddenOperationException(ForbiddenOperationException::LAST_FILE);
        }
    }

    private function validateSlot(Asset $asset, string $slot): void
    {
        $assetTypeConfiguration = $this->extSystemConfigurationProvider->getAssetConfiguration(
            $asset->getLicence()->getExtSystem()->getSlug(),
            $asset->getAttributes()->getAssetType()
        );

        if (in_array($slot, $assetTypeConfiguration->getFileVersions()->getVersions(), true)) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_FILE_VERSION);
    }
}
