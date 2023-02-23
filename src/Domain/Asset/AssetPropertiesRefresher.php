<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use Doctrine\ORM\NonUniqueResultException;

class AssetPropertiesRefresher extends AbstractManager
{
    public function __construct(
        private readonly AssetTextsProcessor $assetTextsProcessor,
        private readonly DistributionRepository $distributionRepository,
    ) {
    }

    /**
     * Used for refresh RO properties e.g. display title, main file, ...
     *
     * @throws NonUniqueResultException
     */
    public function refreshProperties(Asset $asset): Asset
    {
        $this->assetTextsProcessor->updateAssetDisplayTitle($asset);

        $this->syncMainFile($asset);
        $this->refreshMainFile($asset);
        $this->refreshStatus($asset);
        $this->refreshAssetFileProperties($asset);

        return $asset;
    }

    public function refreshAssetFileProperties(Asset $asset): void
    {
        $this->refreshDistributionServices($asset);
        $this->refreshSlotNames($asset);
        $this->refreshFromRss($asset);
        $this->refreshDimensionFields($asset);
    }

    private function refreshDistributionServices(Asset $asset): void
    {
        $distributions = $this->distributionRepository->findByAsset((string) $asset->getId());
        $asset->getAssetFileProperties()->setDistributesInServices(
            array_values(array_unique(
                $distributions
                    ->filter(
                        fn (Distribution $distribution): bool => $distribution->getStatus()->is(DistributionProcessStatus::Distributed),
                    )
                    ->map(
                        fn (Distribution $distribution): string => $distribution->getDistributionService(),
                    )->toArray()
            ))
        );
    }

    private function refreshSlotNames(Asset $asset): void
    {
        $asset->getAssetFileProperties()->setSlotNames(
            array_unique(
                $asset->getSlots()->map(
                    fn (AssetSlot $slot): string => $slot->getName(),
                )->toArray()
            )
        );
    }

    private function refreshFromRss(Asset $asset): void
    {
        $asset->getAssetFileProperties()->setFromRss(
            App::ZERO < $asset->getEpisodes()->filter(
                fn (PodcastEpisode $episode): bool => $episode->getFlags()->isFromRss()
            )->count()
        );
    }

    private function refreshDimensionFields(Asset $asset): void
    {
        $mainFile = $asset->getMainFile();
        if (null === $mainFile) {
            return;
        }

        if ($mainFile instanceof ImageFile) {
            $asset->getAssetFileProperties()->setWidth(
                $mainFile->getImageAttributes()->getWidth()
            );
            $asset->getAssetFileProperties()->setHeight(
                $mainFile->getImageAttributes()->getHeight()
            );
        }
        if ($mainFile instanceof VideoFile) {
            $asset->getAssetFileProperties()->setWidth(
                $mainFile->getAttributes()->getWidth()
            );
            $asset->getAssetFileProperties()->setHeight(
                $mainFile->getAttributes()->getHeight()
            );
        }
    }

    /**
     * Updates slot flags based on asset main file
     */
    private function syncMainFile(Asset $asset): void
    {
        $asset->getSlots()->map(
            fn (AssetSlot $slot) => $slot->getFlags()->setMain(
                $slot->getAssetFile() === $asset->getMainFile()
            )
        );
    }

    private function refreshStatus(Asset $asset): void
    {
        if ($asset->getAttributes()->getStatus()->is(AssetStatus::Deleting)) {
            return;
        }

        foreach ($asset->getSlots() as $slot) {
            if ($slot->getAssetFile()->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)) {
                $asset->getAttributes()->setStatus(AssetStatus::WithFile);

                return;
            }
        }

        $asset->getAttributes()->setStatus(AssetStatus::Draft);
    }

    /**
     * If there is no main file, try to set new main file
     */
    private function refreshMainFile(Asset $asset): void
    {
        if ($asset->getMainFile()) {
            return;
        }

        $newMainFileSlot = $this->getDefaultSlot($asset) ?? $asset->getSlots()->first();
        if ($newMainFileSlot instanceof AssetSlot) {
            $newMainFileSlot->getFlags()->setMain(true);
            $asset->setMainFile($newMainFileSlot->getAssetFile());

            return;
        }

        $asset->setMainFile(null);
    }

    private function getDefaultSlot(Asset $asset): ?AssetSlot
    {
        foreach ($asset->getSlots() as $slot) {
            if ($slot->getFlags()->isDefault()) {
                return $slot;
            }
        }

        return null;
    }
}
