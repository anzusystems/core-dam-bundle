<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\UsageClaim;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageReleaseRequestDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use Throwable;

/**
 * The counterpart of {@see ImageUseFacade}'s claim: drops the holder from every take-over group of the
 * batch, but only where the caller actually holds a given group — a stale or wrong release must never free
 * a photo someone else legitimately claimed since. Idempotent and per-group: an id the holder never held,
 * or holds no more, is silently skipped, the rest of the batch still releases.
 */
final class ImageReleaseFacade
{
    use ValidatorAwareTrait;

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private readonly AssetFileManager $assetFileManager,
        private readonly AssetFileRepository $assetFileRepository,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function releaseImages(ImageReleaseRequestDto $dto): void
    {
        $this->validator->validate($dto);

        $rootIds = [];
        foreach ($dto->getImageFileIds() as $imageFile) {
            $rootIds[$imageFile->getTakeOverRootId()] = true;
        }
        $rootIds = array_keys($rootIds);
        if ([] === $rootIds) {
            return;
        }
        $claim = UsageClaim::fromHolder($dto->getHolder());

        try {
            $this->assetFileManager->beginTransaction();
            // Same lock ImageUseFacade takes: a use racing this release must see one consistent state,
            // holder either already gone or still here to compare against.
            $groupByRoot = $this->assetFileRepository->findGroupFilesByRoot($rootIds, lock: true);
            $this->releaseGroupsHeldBy($groupByRoot, $claim);
            $this->assetFileManager->flush();
            $this->assetFileManager->commit();
        } catch (Throwable $exception) {
            if ($this->assetFileManager->isTransactionActive()) {
                $this->assetFileManager->rollback();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, list<AssetFile>> $groupByRoot
     */
    private function releaseGroupsHeldBy(array $groupByRoot, UsageClaim $claim): void
    {
        $released = UsageClaim::released();
        foreach ($groupByRoot as $rootGroup) {
            if (false === $this->isHeldBy($rootGroup, $claim)) {
                continue;
            }

            foreach ($rootGroup as $file) {
                // The caller decided from its own state, which may have moved on since — a photo it dropped
                // and took again reads as unused right up to the commit. One more question to the ext system
                // turns a wrong release into a holder the reconcile writes back, instead of a photo offered
                // as free with no check date left to notice it.
                $this->assetFileManager->updateUsage($file, $released, usedByCheckAfter: AssetFileManager::nextUsageCheck(), flush: false);
            }
        }
    }

    /**
     * @param list<AssetFile> $group
     */
    private function isHeldBy(array $group, UsageClaim $claim): bool
    {
        foreach ($group as $file) {
            $attributes = $file->getAssetAttributes();
            if (App::EMPTY_STRING === $attributes->getUsedByHolderName()) {
                continue;
            }

            return $claim->matches($attributes);
        }

        return false;
    }
}
