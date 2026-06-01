<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetSlot;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeConfiguration;
use InvalidArgumentException;

readonly class AssetSlotFactory
{
    public function __construct(
        private AssetSlotManager $manager,
        private ExtSystemConfigurationProvider $configurationProvider,
    ) {
    }

    public function createRelation(Asset $asset, AssetFile $assetFile, ?string $slotName = null, bool $flush = true): AssetSlot
    {
        $isMainSlot = $asset->getSlots()->isEmpty();

        $assetSlot = $this->initRelationEntity($asset, $slotName);
        $asset->addSlot($assetSlot);
        $assetFile->addSlot($assetSlot);
        $assetFile->setAsset($asset);

        if ($isMainSlot) {
            $assetSlot->getFlags()->setMain(true);
            $asset->setMainFile($assetFile);
        }

        match ($assetFile::class) {
            ImageFile::class => $assetSlot->setImage($assetFile),
            AudioFile::class => $assetSlot->setAudio($assetFile),
            DocumentFile::class => $assetSlot->setDocument($assetFile),
            VideoFile::class => $assetSlot->setVideo($assetFile),
            default => throw new InvalidArgumentException(sprintf('Unsupported asset file type: %s', $assetFile::class)),
        };

        return $this->manager->create($assetSlot, $flush);
    }

    /**
     * Points the asset's named slot at {@see $newFile}, returning the file it previously held (or null
     * if the slot did not exist yet — in which case a fresh relation is created like {@see createRelation}).
     *
     * The previous file is left attached to the asset (its `asset` FK is untouched, so the required
     * relation invariant holds) but detached from the slot — TTS regeneration uses this to keep the old
     * audio (and its public-bucket CDN URL) alive while the new file gets a new slot/URL. The caller owns
     * the previous file's lifecycle from here (e.g. {@see AssetFile::setExpireAt()} for grace cleanup).
     *
     * Caller owns the surrounding transaction/flush.
     */
    public function replaceSlotFile(Asset $asset, AssetFile $newFile, string $slotName): ?AssetFile
    {
        $slot = $asset->getSlots()->findFirst(
            static fn (mixed $key, AssetSlot $candidate): bool => $candidate->getName() === $slotName
        );

        if (false === $slot instanceof AssetSlot) {
            $this->createRelation($asset, $newFile, $slotName, false);

            return null;
        }

        $previousFile = $this->currentSlotFile($slot);
        $previousFile?->getSlots()->removeElement($slot);

        // addSlot() also points the slot back at the new file (owning side); setAsset keeps the required FK.
        $newFile->addSlot($slot);
        $newFile->setAsset($asset);

        if ($slot->getFlags()->isMain()) {
            $asset->setMainFile($newFile);
        }

        return $previousFile;
    }

    public function getSlotName(
        ExtSystemAssetTypeConfiguration $configuration,
        ?string $slotName = null
    ): string {
        if (empty($slotName)) {
            return $configuration->getSlots()->getDefault();
        }

        if (in_array($slotName, $configuration->getSlots()->getSlots(), true)) {
            return $slotName;
        }

        throw new DomainException('invalid_slot_name');
    }

    private function initRelationEntity(Asset $asset, ?string $slotName = null): AssetSlot
    {
        $configuration = $this->configurationProvider->getExtSystemConfigurationByAsset($asset);
        $assetSlot = new AssetSlot();

        $actualSlotName = $this->getSlotName($configuration, $slotName);

        $assetSlot->setName($actualSlotName);
        $assetSlot->getFlags()->setDefault($configuration->getSlots()->getDefault() === $actualSlotName);

        return $assetSlot;
    }

    private function currentSlotFile(AssetSlot $slot): ?AssetFile
    {
        return $slot->getImage()
            ?? $slot->getAudio()
            ?? $slot->getVideo()
            ?? $slot->getDocument();
    }
}
