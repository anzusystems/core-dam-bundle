<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetFile\AssetFileUsageReconcileResult;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\UsageClaim;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use DateTimeImmutable;
use Throwable;

/**
 * Frees single use photos whose holder never materialised on the other side — a claim the ext system
 * accepted and then rolled back, or one it lost to a crash. The ext system is asked about the whole
 * take-over group, because a copy is what it points at once a photo was taken over.
 */
final readonly class AssetFileUsageReconciler
{
    private const int PAGE_SIZE = 100;

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private AssetFileRepository $assetFileRepository,
        private AssetFileManager $assetFileManager,
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
        private DamLogger $damLogger,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function reconcile(int $limit): AssetFileUsageReconcileResult
    {
        $now = App::getAppDate();
        $idFrom = App::EMPTY_STRING;
        $checked = App::ZERO;
        $confirmed = App::ZERO;
        $released = App::ZERO;
        $skipped = App::ZERO;

        while ($checked < $limit) {
            $due = $this->assetFileRepository->findUsageChecksDue($now, min(self::PAGE_SIZE, $limit - $checked), $idFrom);
            if ([] === $due) {
                break;
            }

            $idFrom = (string) $due[array_key_last($due)]->getId();
            $checked += count($due);

            foreach ($this->usedByRoot($due) as $rootId => $used) {
                $written = $this->settleGroup($rootId, $used, $now);
                if (false === $written) {
                    $skipped++;

                    continue;
                }
                if ($used) {
                    $confirmed++;

                    continue;
                }
                $released++;
            }
        }

        return new AssetFileUsageReconcileResult($checked, $confirmed, $released, $skipped);
    }

    /**
     * @param list<AssetFile> $due
     *
     * @return array<string, bool> take-over root id => the ext system still points at some file of the group
     */
    private function usedByRoot(array $due): array
    {
        $rootIds = array_values(array_unique(array_map(
            static fn (AssetFile $assetFile): string => $assetFile->getTakeOverRootId(),
            $due,
        )));

        $group = $this->assetFileRepository->findGroupFiles($rootIds);
        $imageFiles = array_filter($group, static fn (AssetFile $assetFile): bool => $assetFile instanceof ImageFile);
        // Fails closed: an ext system that cannot answer keeps its photos held.
        $usage = $this->extSystemCallbackFacade->isImageFileUsedBulk($imageFiles);

        $usedByRoot = array_fill_keys($rootIds, false);
        foreach ($group as $assetFile) {
            if ($usage[(string) $assetFile->getId()] ?? true) {
                $usedByRoot[$assetFile->getTakeOverRootId()] = true;
            }
        }

        return $usedByRoot;
    }

    /**
     * @return bool whether the group was written; false when a claim arrived while the ext system was asked
     *
     * @throws Throwable
     */
    private function settleGroup(string $rootId, bool $used, DateTimeImmutable $now): bool
    {
        try {
            $this->assetFileManager->beginTransaction();
            // The same lock a claim and a release take: a claim that lands between the question above and
            // this write pushes its own check date forward, and the group is left to the next run.
            $group = $this->assetFileRepository->findGroupFiles([$rootId], lock: true);
            $stillDue = array_filter($group, static fn (AssetFile $assetFile): bool => self::isDue($assetFile, $now));
            if ([] === $stillDue) {
                $this->assetFileManager->rollback();

                return false;
            }

            foreach ($group as $assetFile) {
                if ($used) {
                    $assetFile->getAssetAttributes()->setUsedByCheckAfter(null);

                    continue;
                }

                $this->assetFileManager->updateUsage($assetFile, UsageClaim::released(), flush: false);
            }
            $this->assetFileManager->flush();
            $this->assetFileManager->commit();
        } catch (Throwable $exception) {
            if ($this->assetFileManager->isTransactionActive()) {
                $this->assetFileManager->rollback();
            }

            throw $exception;
        }

        if (false === $used) {
            $this->damLogger->warning(
                DamLogger::NAMESPACE_EXT_SYSTEM_CALLBACK,
                sprintf('Single use group %s released: the ext system points at none of its files', $rootId),
            );
        }

        return true;
    }

    private static function isDue(AssetFile $assetFile, DateTimeImmutable $now): bool
    {
        $checkAfter = $assetFile->getAssetAttributes()->getUsedByCheckAfter();

        return $checkAfter instanceof DateTimeImmutable && $checkAfter <= $now;
    }
}
