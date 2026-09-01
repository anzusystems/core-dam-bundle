<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicence;

use AnzuSystems\CommonBundle\Traits\EntityManagerAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetLicenceAutoDelete;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetEventDispatcher;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileDeleteEventDispatcher;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Traits\FileStashAwareTrait;
use Throwable;

final class AssetLicenceRetentionFacade
{
    use EntityManagerAwareTrait;
    use FileStashAwareTrait;

    private const int ASSET_BULK_SIZE = 100;

    public function __construct(
        private readonly AssetLicenceRepository $assetLicenceRepository,
        private readonly AssetRepository $assetRepository,
        private readonly AssetFacade $assetFacade,
        private readonly AssetEventDispatcher $assetEventDispatcher,
        private readonly AssetFileDeleteEventDispatcher $assetFileDeleteEventDispatcher,
        private readonly DamLogger $damLogger,
        private int $bulkSize = self::ASSET_BULK_SIZE,
    ) {
    }

    public function setBulkSize(int $bulkSize): void
    {
        $this->bulkSize = $bulkSize;
    }

    public function deleteExpiredAssets(): int
    {
        $deleted = App::ZERO;

        foreach ($this->assetLicenceRepository->findAllWithAutoDeleteActive() as $licence) {
            $licenceId = $licence->getId();

            if ($licence->getAutoDelete()->getOlderThanDays() < AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS) {
                $this->damLogger->warning(
                    DamLogger::NAMESPACE_ASSET_LICENCE_RETENTION,
                    sprintf('Licence (%d) skipped, olderThanDays below %d', $licenceId, AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS),
                );

                continue;
            }

            $deleted += $this->deleteExpiredAssetsForLicence($licenceId);
        }

        return $deleted;
    }

    private function deleteExpiredAssetsForLicence(int $licenceId): int
    {
        $deleted = App::ZERO;
        $idFrom = null;
        $licence = $this->assetLicenceRepository->find($licenceId);

        while ($licence instanceof AssetLicence && $licence->getAutoDelete()->isActive()) {
            $createdBefore = App::getAppDate()->modify(sprintf('-%d days', $licence->getAutoDelete()->getOlderThanDays()));
            $assets = $this->assetRepository->findByLicenceRetention($licence, $createdBefore, $this->bulkSize, $idFrom);
            if ($assets->isEmpty()) {
                return $deleted;
            }

            $idFrom = (string) $assets->last()->getId();

            try {
                $this->entityManager->beginTransaction();
                $deleted += $this->assetFacade->deleteBulkForRetention($assets);
                $this->entityManager->commit();
            } catch (Throwable $throwable) {
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->rollback();
                }

                throw $throwable;
            }

            // Committed — storage cleanup and events run after, and must not report the batch as failed.
            try {
                $this->fileStash->emptyAll();
                $this->assetFileDeleteEventDispatcher->dispatchAll();
                $this->assetEventDispatcher->dispatchAll();
            } catch (Throwable $throwable) {
                $this->damLogger->error(
                    DamLogger::NAMESPACE_ASSET_LICENCE_RETENTION,
                    sprintf('Post-delete cleanup failed for licence (%d)', $licenceId),
                    exception: $throwable,
                );
            }

            $this->entityManager->clear();
            $licence = $this->assetLicenceRepository->find($licenceId);
        }

        return $deleted;
    }
}
