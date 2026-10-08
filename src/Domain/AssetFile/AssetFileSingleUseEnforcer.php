<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
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
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
        private DamLogger $damLogger,
        private bool $singleUseEnforced,
    ) {
    }

    public function enforce(AssetFile $assetFile): bool
    {
        if ($assetFile->getFlags()->isSingleUse() || false === $this->mustBeSingleUse($assetFile)) {
            return false;
        }
        if (false === $this->allowSwitchToSingleUse($assetFile)) {
            return false;
        }

        $assetFile->getFlags()->setSingleUse(true);
        $this->armUsageCheck($assetFile);

        return true;
    }

    /**
     * @throws ForbiddenOperationException
     */
    public function switchSingleUse(AssetFile $assetFile, bool $singleUse): void
    {
        $becomesSingleUse = $singleUse && false === $assetFile->getFlags()->isSingleUse();
        if ($becomesSingleUse && false === $this->allowSwitchToSingleUse($assetFile)) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_SINGLE_USE_AFTER_FIRST_USE);
        }

        $assetFile->getFlags()->setSingleUse($singleUse);
        if ($becomesSingleUse) {
            $this->armUsageCheck($assetFile);
        }
    }

    /**
     * Backfill for files created before the rule applied to their licence; returns the number of
     * files switched to single use. Runs in batches and reindexes each batch, so it is safe for
     * licences with tens of thousands of assets. The ext system mirrors the flag, so every switched
     * image is announced to it the same way an ordinary edit is — a mirror that lags behind makes the
     * host treat a single use photo as a plain one.
     */
    public function enforceLicence(AssetLicence $licence): int
    {
        $enforcedCount = App::ZERO;
        $idFrom = App::EMPTY_STRING;

        do {
            $assets = $this->assetRepository->findAllByLicence($licence, self::BATCH_SIZE, $idFrom);
            $switchedImages = [];
            foreach ($assets as $asset) {
                foreach ($asset->getSlots() as $slot) {
                    $assetFile = $slot->getAssetFile();
                    if (false === $this->enforce($assetFile)) {
                        continue;
                    }
                    $enforcedCount++;
                    if ($assetFile instanceof ImageFile) {
                        $switchedImages[] = $assetFile;
                    }
                }
                $idFrom = (string) $asset->getId();
            }

            $this->entityManager->flush();
            if (false === $assets->isEmpty()) {
                $this->indexManager->indexBulk($assets->toArray());
            }
            $this->extSystemCallbackFacade->notifyImagesChanged(new ArrayCollection($switchedImages));
            $this->entityManager->clear();
        } while (self::BATCH_SIZE === $assets->count());

        return $enforcedCount;
    }

    /**
     * Every path that turns a file single use ends here, not just the licence rule above: nobody holds it in
     * the register yet, and only the ext system knows who really uses it. The reconcile discovers that, but
     * only for files it can see ({@see \AnzuSystems\CoreDamBundle\Repository\AssetFileRepository::findUsageChecksDue}),
     * so a file that becomes single use without a check date stays outside its reach for good.
     */
    private function armUsageCheck(AssetFile $assetFile): void
    {
        $assetFile->getAssetAttributes()->setUsedByCheckAfter(AssetFileManager::nextUsageCheck());
    }

    /**
     * A file somebody has already used cannot become single use: DAM does not know how many holders point
     * at it, so exclusivity could not be honoured for it. Until every host sends a holder, the switch is
     * only logged and still allowed (#85974).
     */
    private function allowSwitchToSingleUse(AssetFile $assetFile): bool
    {
        if (false === $assetFile->getFirstUsedAt() instanceof DateTimeImmutable) {
            return true;
        }

        $this->damLogger->warning(
            DamLogger::NAMESPACE_ASSET_FILE_PROCESS,
            sprintf('Asset file %s switched to single use after its first use', (string) $assetFile->getId()),
        );

        return false === $this->singleUseEnforced;
    }

    private function mustBeSingleUse(AssetFile $assetFile): bool
    {
        return $assetFile->getLicence()->getFlags()->isSingleUseEnforced();
    }
}
