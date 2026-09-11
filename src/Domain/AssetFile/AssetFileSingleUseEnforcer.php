<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The one place deciding which asset files must be single use; today the rule is the licence
 * flag, further rules (e.g. by file name) belong here as well.
 */
final readonly class AssetFileSingleUseEnforcer
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private AssetRepository $assetRepository,
        private EntityManagerInterface $entityManager,
        private IndexManager $indexManager,
    ) {
    }

    public function enforce(AssetFile $assetFile): bool
    {
        if ($assetFile->getFlags()->isSingleUse() || false === $this->mustBeSingleUse($assetFile)) {
            return false;
        }

        $assetFile->getFlags()->setSingleUse(true);

        return true;
    }

    /**
     * Backfill for files created before the rule applied to their licence; returns the number of
     * files switched to single use. Runs in batches and reindexes each batch, so it is safe for
     * licences with tens of thousands of assets.
     */
    public function enforceLicence(AssetLicence $licence): int
    {
        $enforcedCount = App::ZERO;
        $idFrom = App::EMPTY_STRING;

        do {
            $assets = $this->assetRepository->findAllByLicence($licence, self::BATCH_SIZE, $idFrom);
            foreach ($assets as $asset) {
                foreach ($asset->getSlots() as $slot) {
                    if ($this->enforce($slot->getAssetFile())) {
                        $enforcedCount++;
                    }
                }
                $idFrom = (string) $asset->getId();
            }

            $this->entityManager->flush();
            if (false === $assets->isEmpty()) {
                $this->indexManager->indexBulk($assets->toArray());
            }
            $this->entityManager->clear();
        } while (self::BATCH_SIZE === $assets->count());

        return $enforcedCount;
    }

    private function mustBeSingleUse(AssetFile $assetFile): bool
    {
        return $assetFile->getLicence()->getFlags()->isSingleUseEnforced();
    }
}
