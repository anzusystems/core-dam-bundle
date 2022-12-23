<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Traits\ExtSystemConfigurationProviderAwareTrait;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

/**
 * @template-covariant T of AssetFile
 */
abstract class AssetFilePositionFacade
{
    use IndexManagerAwareTrait;
    use ExtSystemConfigurationProviderAwareTrait;

    private AssetSlotFactory $assetSlotFactory;
    private AssetSlotManager $assetSlotManager;
    private AssetManager $assetManager;

    #[Required]
    public function setAssetSlotFactory(AssetSlotFactory $assetSlotFactory): void
    {
        $this->assetSlotFactory = $assetSlotFactory;
    }

    #[Required]
    public function setAssetSlotManager(AssetSlotManager $assetSlotManager): void
    {
        $this->assetSlotManager = $assetSlotManager;
    }

    #[Required]
    public function setAssetManager(AssetManager $assetManager): void
    {
        $this->assetManager = $assetManager;
    }

    /**
     * @return T
     */
    public function setMainFile(Asset $asset, AssetFile $assetFile): AssetFile
    {
        if (false === ($assetFile->getAsset() === $asset)) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        try {
            $this->assetManager->beginTransaction();
            $asset->setMainFile($assetFile);
            $this->assetManager->updateExisting($asset);
            $this->indexManager->index($asset);
            $this->assetManager->commit();

            return $assetFile;
        } catch (Throwable $exception) {
            $this->assetManager->rollback();

            throw new RuntimeException('set_main_position_failed', 0, $exception);
        }
    }

    /**
     * @return T
     */
    public function setToSlot(Asset $asset, AssetFile $assetFile, string $slotName): AssetFile
    {
        $this->validateSlot($asset, $slotName);
        $this->validate($asset, $assetFile, $slotName);

        try {
            $this->assetManager->beginTransaction();
            $originAsset = $assetFile->getAsset();

            $this->removeOtherAssetSlots($asset, $assetFile);
            $this->assetSlotFactory->createRelation($asset, $assetFile, $slotName, false);
            $assetFile->setAsset($asset);

            if (false === ($asset === $originAsset)) {
                $this->assetManager->updateExisting($originAsset, false);
            }
            $this->assetManager->updateExisting($asset);
            $this->indexManager->index($asset);
            if (false === ($asset === $originAsset)) {
                $this->indexManager->index($originAsset);
            }
            $this->assetManager->commit();

            return $assetFile;
        } catch (Throwable $exception) {
            $this->assetManager->rollback();

            throw new RuntimeException('add_to_slot_failed', 0, $exception);
        }
    }

    private function removeOtherAssetSlots(Asset $asset, AssetFile $assetFile): void
    {
        foreach ($assetFile->getSlots() as $slot) {
            if ($slot->getAsset() === $asset) {
                continue;
            }

            $this->assetSlotManager->delete($slot, false);
        }
    }

    private function validate(Asset $asset, AssetFile $assetFile, string $slotName): void
    {
        foreach ($asset->getSlots() as $slot) {
            if ($slot->getAssetFile() === $assetFile && $slot->getName() === $slotName) {
                throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
            }
        }

        if (false === ($asset->getAttributes()->getAssetType() === $assetFile->getAssetType())) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_ASSET_TYPE);
        }

        if (false === ($asset->getLicence() === $assetFile->getLicence())) {
            throw new ForbiddenOperationException(ForbiddenOperationException::LICENCE_MISMATCH);
        }
    }

    private function validateSlot(Asset $asset, string $slot): void
    {
        $assetTypeConfiguration = $this->extSystemConfigurationProvider->getAssetConfiguration(
            $asset->getLicence()->getExtSystem()->getSlug(),
            $asset->getAttributes()->getAssetType()
        );

        if (in_array($slot, $assetTypeConfiguration->getSlots()->getSlots(), true)) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_ASSET_SLOT);
    }
}
