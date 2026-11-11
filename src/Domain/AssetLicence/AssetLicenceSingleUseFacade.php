<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicence;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileSingleUseEnforcer;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AssetLicenceSingleUseFacade
{
    private const int BATCH_SIZE = 500;

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private AssetRepository $assetRepository,
        private AssetFileManager $assetFileManager,
        private IndexManager $indexManager,
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
        private AssetFileSingleUseEnforcer $assetFileSingleUseEnforcer,
    ) {
    }

    /**
     * Backfill for files created before the rule applied to their licence; returns the number of
     * files switched to single use. Runs in batches and reindexes each batch, so it is safe for
     * licences with tens of thousands of assets. The ext system mirrors the flag, so every switched
     * image is announced to it the same way an ordinary edit is — a mirror that lags behind makes the
     * host treat a single use photo as a plain one.
     */
    public function enforce(AssetLicence $licence): int
    {
        $enforcedCount = App::ZERO;
        $idFrom = App::EMPTY_STRING;

        do {
            $assets = $this->assetRepository->findAllByLicence($licence, self::BATCH_SIZE, $idFrom);
            $switchedImages = [];
            foreach ($assets as $asset) {
                foreach ($asset->getSlots() as $slot) {
                    $assetFile = $slot->getAssetFile();
                    if (false === $this->assetFileSingleUseEnforcer->enforce($assetFile)) {
                        continue;
                    }
                    $enforcedCount++;
                    if ($assetFile instanceof ImageFile) {
                        $switchedImages[] = $assetFile;
                    }
                }
                $idFrom = (string) $asset->getId();
            }

            $this->assetFileManager->flush();
            if (false === $assets->isEmpty()) {
                $this->indexManager->indexBulk($assets->toArray());
            }
            $this->extSystemCallbackFacade->notifyImagesChanged(new ArrayCollection($switchedImages));
            $this->assetFileManager->clear();
        } while (self::BATCH_SIZE === $assets->count());

        return $enforcedCount;
    }
}
