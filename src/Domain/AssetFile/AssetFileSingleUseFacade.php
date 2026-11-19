<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Backfill for files created before a single use rule applied to them. The ext system is notified
 * before the flush: a failed notification leaves the batch unswitched, so a re-run announces it again.
 * Only assets with a switched file are reindexed, so a re-run does not repair the index after a crash between
 * the flush and the reindex.
 */
final readonly class AssetFileSingleUseFacade
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

    public function enforceForLicence(AssetLicence $licence): int
    {
        return $this->enforce(
            fn (string $idFrom): Collection => $this->assetRepository->findAllByLicence($licence, self::BATCH_SIZE, $idFrom),
        );
    }

    public function enforceForSource(Author $source): int
    {
        return $this->enforce(
            fn (string $idFrom): Collection => $this->assetRepository->findAllBySource($source, self::BATCH_SIZE, $idFrom),
        );
    }

    /**
     * @param Closure(string): Collection<int, Asset> $findBatch
     */
    private function enforce(Closure $findBatch): int
    {
        $enforcedCount = App::ZERO;
        $idFrom = App::EMPTY_STRING;

        do {
            $assets = $findBatch($idFrom);
            $switchedImages = [];
            $switchedAssets = [];
            foreach ($assets as $asset) {
                foreach ($asset->getSlots() as $slot) {
                    $assetFile = $slot->getAssetFile();
                    if (false === $this->assetFileSingleUseEnforcer->enforce($assetFile)) {
                        continue;
                    }
                    $enforcedCount++;
                    $switchedAssets[(string) $asset->getId()] = $asset;
                    if ($assetFile instanceof ImageFile) {
                        $switchedImages[] = $assetFile;
                    }
                }
                $idFrom = (string) $asset->getId();
            }

            $this->extSystemCallbackFacade->notifyImagesChanged(new ArrayCollection($switchedImages));
            $this->assetFileManager->flush();
            if (false === empty($switchedAssets)) {
                $this->indexManager->indexBulk(array_values($switchedAssets));
            }
            $this->assetFileManager->clear();
        } while (self::BATCH_SIZE === $assets->count());

        return $enforcedCount;
    }
}
