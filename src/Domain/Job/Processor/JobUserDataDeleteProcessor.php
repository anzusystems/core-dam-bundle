<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;
use AnzuSystems\CommonBundle\Traits\EntityManagerAwareTrait;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceFacade;
use AnzuSystems\CoreDamBundle\Domain\User\UserManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetEventDispatcher;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileDeleteEventDispatcher;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use Throwable;

final class JobUserDataDeleteProcessor extends AbstractJobProcessor
{
    use EntityManagerAwareTrait;

    private const int ASSET_BULK_SIZE = 10;

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly UserManager $userManager,
        private readonly AssetFacade $assetFacade,
        private readonly AssetLicenceFacade $licenceFacade,
        private readonly AssetEventDispatcher $assetEventDispatcher,
        private readonly AssetFileDeleteEventDispatcher $assetFileDeleteEventDispatcher,
        private int $bulkSize = self::ASSET_BULK_SIZE,
    ) {
    }

    public static function getSupportedJob(): string
    {
        return JobUserDataDelete::class;
    }

    public function setBulkSize(int $bulkSize): void
    {
        $this->bulkSize = $bulkSize;
    }

    /**
     * @param JobUserDataDelete $job
     */
    public function process(JobInterface $job): void
    {
        /** @var DamUser $user */
        $user = $this->entityManager->find(AnzuUser::class, $job->getTargetUserId());
        $licencesWithUserOnlyMembership = $user->getAssetLicences()->filter(
            fn (AssetLicence $licence): bool => 1 === $licence->getUsers()->count(),
        );
        $licencesWithUserOnlyMembershipIds = CollectionHelper::traversableToIds($licencesWithUserOnlyMembership);

        $this->start($job);

        try {
            $this->entityManager->beginTransaction();

            $assets = $this->assetRepository->geAllByLicenceIds(
                licenceIds: $licencesWithUserOnlyMembershipIds,
                limit: $this->bulkSize,
                idFrom: $job->getLastBatchProcessedRecord() ?: null
            );
            $removedCount = $this->assetFacade->deleteBulk($assets);
            if (0 === $removedCount && $job->isAnonymizeUser()) {
                $this->licenceFacade->deleteBulk($licencesWithUserOnlyMembership);
                $this->userManager->deletePersonalData($user, false);
            }
            $this->finishProcessCycle($job, $removedCount, $assets->last() ?: null);
            $this->entityManager->commit();

            $this->assetFileDeleteEventDispatcher->dispatchAll();
            $this->assetEventDispatcher->dispatchAll();
        } catch (Throwable $throwable) {
            $this->entityManager->rollback();
            $this->finishFail($job, substr($throwable->getMessage(), 0, 255));
        }
    }

    private function finishProcessCycle(JobUserDataDelete $job, int $removedCount, ?Asset $lastRemovedAsset): void
    {
        if (0 === $removedCount) {
            $this->getManagedJob($job)->setResult('Delete job was successfully processed!');
            $this->finishSuccess($job);

            return;
        }

        $job = $this->getManagedJob($job)->setResult("Deleted assets in batch: {$removedCount}");

        $this->toAwaitingBatchProcess($job, (string) $lastRemovedAsset?->getId());
    }
}
