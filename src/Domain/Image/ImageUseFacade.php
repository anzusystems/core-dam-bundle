<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetPropertiesRefresher;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFirstUseFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\ImageUsageConflictException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\ImageUseResolution;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\UsageClaim;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\AssetFileCopyResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUsageConflictDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultListDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Decides whether each image of a batch may be used as it is, or has to be taken over into the caller's
 * licence first, and — for a single use photo — claims it for the caller. The licence flags own the
 * take-over rule and DAM alone owns exclusivity, so the caller never decides either, it only receives the
 * files to use. The first successful use of every file of the batch is recorded here; from then on the
 * file can no longer be switched to single use.
 *
 * The whole batch is resolved and claimed under one transaction: a gallery of 20 photos costs one request,
 * and either all of them end up usable or none does.
 *
 * Unlike {@see ImageCopyFacade::prepareCopyList()} every copy is finished inside the request: the caller
 * stores the returned ids immediately, so a target still being filled asynchronously would be referenced
 * before it has any data.
 */
final class ImageUseFacade
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private readonly ImageCopyFacade $imageCopyFacade,
        private readonly AssetFileFirstUseFacade $assetFileFirstUseFacade,
        private readonly AssetManager $assetManager,
        private readonly AssetPropertiesRefresher $assetPropertiesRefresher,
        private readonly AssetFileManager $assetFileManager,
        private readonly AssetFileRepository $assetFileRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly DamLogger $damLogger,
        private readonly bool $singleUseEnforced,
    ) {
    }

    /**
     * @throws ForbiddenOperationException
     * @throws ImageUsageConflictException
     * @throws Throwable
     */
    public function useImages(ImageUseRequestDto $dto): ImageUseResultListDto
    {
        $this->validator->validate($dto);

        try {
            $this->entityManager->beginTransaction();
            // The whole batch — resolution and, for single use items, the claim — runs under one
            // transaction: a conflict or a refusal on any item must leave the database exactly as it was.
            $resolved = [];
            foreach ($dto->getItems() as $item) {
                $resolved[] = $this->resolveItem($item);
            }
            $this->claimSingleUseFiles($resolved, $dto->getHolder(), $dto->getReleaseFrom());
            $this->assetFileFirstUseFacade->record(
                array_map(static fn (ImageUseResolution $resolution): AssetFile => $resolution->getFile(), $resolved),
                App::getAppDate(),
            );

            $results = new ArrayCollection();
            foreach ($resolved as $resolution) {
                $results->add(ImageUseResultDto::getInstance($resolution->getFile(), $resolution->isTakenOver()));
            }
            $result = ImageUseResultListDto::getInstance($results);
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            // Only the DB is rolled back, so no half prepared target stays behind with no file and no owner.
            // Bytes already written to the bucket are not transactional and remain there as orphans.
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $exception;
        }

        return $result;
    }

    /**
     * @throws ForbiddenOperationException
     * @throws Throwable
     */
    private function resolveItem(ImageUseItemDto $item): ImageUseResolution
    {
        $source = $item->getImageFile();
        $this->assertUsableSource($source);

        return $this->resolve($item, $source);
    }

    /**
     * @throws ForbiddenOperationException
     * @throws Throwable
     */
    private function resolve(ImageUseItemDto $item, ImageFile $source): ImageUseResolution
    {
        $targetLicence = $item->getTargetAssetLicence();
        $directUseAllowed = $source->getLicence()->getFlags()->isDirectUseAllowed();

        // The source licence flag decides first: a caller that mislabels the licence in its request must not
        // be able to skip the copy.
        if ($directUseAllowed && false === $item->isForce()) {
            return ImageUseResolution::directUse($source);
        }
        if (false === $targetLicence instanceof AssetLicence) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_DIRECT_USE_DISABLED);
        }

        $sameLicence = $source->getLicence()->is($targetLicence);

        // Asking for the licence the file already lives in leaves nothing to copy, so the source is handed
        // back — but only where using it there is allowed at all. Without the flag the same request is
        // exactly the direct use the licence forbids, no matter which licence the caller names as target.
        if ($sameLicence && $directUseAllowed) {
            return ImageUseResolution::directUse($source);
        }
        if ($sameLicence) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_DIRECT_USE_DISABLED);
        }
        if ($targetLicence->getFlags()->isNotManualUploadAllowed()) {
            throw new ForbiddenOperationException(ForbiddenOperationException::LICENCE_MANUAL_UPLOAD_DISABLED);
        }

        return $this->copyToLicence($source, $targetLicence);
    }

    /**
     * @throws Throwable
     */
    private function copyToLicence(ImageFile $source, AssetLicence $targetLicence): ImageUseResolution
    {
        $copyDto = (new ImageCopyDto())
            ->setAsset($source->getAsset())
            ->setTargetAssetLicence($targetLicence);

        return $this->finishCopy($this->imageCopyFacade->prepareCopy($copyDto), $source);
    }

    /**
     * @throws ForbiddenOperationException
     * @throws Throwable
     */
    private function finishCopy(AssetFileCopyResultDto $prepared, ImageFile $source): ImageUseResolution
    {
        $targetAsset = $prepared->getTargetAsset();
        $targetMainFile = $prepared->getTargetMainFile();
        if (false === $targetAsset instanceof Asset || false === $targetMainFile instanceof AssetFile) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT);
        }

        if ($prepared->getResult()->is(AssetFileCopyStatus::Exists)) {
            // The identical file already lives in the target licence — reused for both single use and
            // shared sources; {@see reuseExisting()} tells a retry of this very take-over apart from a
            // genuine clash with someone else's group.
            return $this->reuseExisting($targetMainFile, $source);
        }

        if (false === $prepared->getResult()->is(AssetFileCopyStatus::Copy)) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT);
        }

        $this->imageCopyFacade->copyAssetSlots($source->getAsset(), $targetAsset);
        $this->assetPropertiesRefresher->refreshProperties($targetAsset);
        $this->assetManager->updateExisting(asset: $targetAsset, trackModification: false);
        $this->indexManager->index($targetAsset);

        return ImageUseResolution::takenOver($targetMainFile);
    }

    /**
     * A file found by checksum did not necessarily get into the target licence by a take over — the same bytes
     * uploaded manually land here too, with no root of their own. Such a file is adopted under the source root,
     * otherwise the caller would store a photo with no link to the original: the exclusivity group would fall
     * apart and the first use would never reach the original.
     *
     * A file already taken over from this exact root is the idempotent case — a retry after a timeout, or the
     * same photo picked twice — and is handed back as-is: {@see useImages()} claims it again below, which is a
     * no-op for a holder that already holds it.
     *
     * A file something else was already taken over from — its own root differs from this one's — belongs to a
     * different exclusivity group. Adopting it would merge two groups into one and leave its own copies pointing
     * at a root their source no longer belongs to, so it is refused instead.
     *
     * @throws ForbiddenOperationException
     */
    private function reuseExisting(AssetFile $existing, ImageFile $source): ImageUseResolution
    {
        $existingRootId = $existing->getAssetAttributes()->getTakenOverFromId();
        if (App::EMPTY_STRING !== $existingRootId) {
            if ($existingRootId === $source->getTakeOverRootId()) {
                return ImageUseResolution::takenOver($existing);
            }

            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT);
        }
        if ($this->assetFileRepository->existsTakenOverFrom((string) $existing->getId())) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT);
        }

        $existing->getAssetAttributes()->setTakenOverFromId($source->getTakeOverRootId());
        // Flushed here, inside the transaction opened by useImages(): committing it does not flush the unit
        // of work, and unlike the copy branch nothing else in this path writes.
        $this->assetFileManager->updateExisting(assetFile: $existing, trackModification: false);

        return ImageUseResolution::takenOver($existing);
    }

    /**
     * Single use exclusivity belongs to the whole take-over group, not to one file, so the holder is written
     * on the root and every copy. A group the caller is handing over from is overwritten in the same write,
     * which is what makes a hand over atomic. Every single use item of the batch is locked in one query
     * {@see AssetFileRepository::findGroupFiles()}, over root ids sorted ascending — deterministic order is
     * the only defense against a deadlock between two concurrent batches that lock an overlapping set of
     * photos in a different order.
     *
     * Every conflict of the batch is collected before anything throws, so the caller learns about all of
     * them at once instead of retrying one item at a time. With enforcement off the conflicts are logged
     * and their groups left alone instead — one switch decides how strict the register is, and the callers
     * only relay its answer.
     *
     * @param list<ImageUseResolution> $resolved
     *
     * @throws ImageUsageConflictException
     */
    private function claimSingleUseFiles(array $resolved, ?ImageHolderDto $holder, ?ImageHolderDto $releaseFrom): void
    {
        $singleUseResolutions = array_values(array_filter(
            $resolved,
            static fn (ImageUseResolution $resolution): bool => $resolution->getFile()->getFlags()->isSingleUse(),
        ));
        if ([] === $singleUseResolutions) {
            return;
        }
        if (null === $holder) {
            $this->refuseSingleUseWithoutHolder($singleUseResolutions);

            return;
        }

        $rootIds = [];
        foreach ($singleUseResolutions as $resolution) {
            $rootIds[$resolution->getFile()->getTakeOverRootId()] = true;
        }
        $rootIds = array_keys($rootIds);
        sort($rootIds);

        $claim = UsageClaim::fromHolder($holder);
        $handOver = $releaseFrom instanceof ImageHolderDto ? UsageClaim::fromHolder($releaseFrom) : null;
        $group = $this->assetFileRepository->findGroupFiles($rootIds, lock: true);

        $groupByRoot = [];
        foreach ($group as $groupFile) {
            $groupByRoot[$groupFile->getTakeOverRootId()][] = $groupFile;
        }

        $conflicts = $this->collectConflicts($singleUseResolutions, $groupByRoot, $claim, $handOver);
        if ([] !== $conflicts && $this->singleUseEnforced) {
            throw new ImageUsageConflictException($conflicts);
        }

        $refused = $this->refusedRoots($singleUseResolutions, $conflicts);
        foreach ($group as $groupFile) {
            // The photos somebody else holds keep their holder; the rest of the batch is still claimed, so
            // a soft mode conflict costs the caller one photo, not the whole save.
            if (isset($refused[$groupFile->getTakeOverRootId()])) {
                continue;
            }

            $this->assetFileManager->updateUsage($groupFile, $claim, flush: false);
        }
        $this->assetFileManager->flush();
    }

    /**
     * The groups of the batch that stay with the holder they already have. Empty in enforced mode, where a
     * conflict has already thrown.
     *
     * @param list<ImageUseResolution> $singleUseResolutions
     * @param list<ImageUsageConflictDto> $conflicts
     *
     * @return array<string, true> take-over root id
     */
    private function refusedRoots(array $singleUseResolutions, array $conflicts): array
    {
        if ([] === $conflicts) {
            return [];
        }

        $refusedFileIds = array_fill_keys(
            array_map(static fn (ImageUsageConflictDto $conflict): string => $conflict->getDamId(), $conflicts),
            true,
        );

        $refused = [];
        foreach ($singleUseResolutions as $resolution) {
            $file = $resolution->getFile();
            if (isset($refusedFileIds[(string) $file->getId()])) {
                $refused[$file->getTakeOverRootId()] = true;
                $this->damLogger->warning(
                    DamLogger::NAMESPACE_EXT_SYSTEM_CALLBACK,
                    sprintf('Single use file %s is held by somebody else, claim ignored: enforcement is off', (string) $file->getId()),
                );
            }
        }

        return $refused;
    }

    /**
     * Exclusivity needs somebody to be exclusive to, so a single use photo cannot be used by a caller with
     * no holder. Until the hosts send one, the refusal is only logged (#85974).
     *
     * @param list<ImageUseResolution> $singleUseResolutions
     *
     * @throws ForbiddenOperationException
     */
    private function refuseSingleUseWithoutHolder(array $singleUseResolutions): void
    {
        foreach ($singleUseResolutions as $resolution) {
            $this->damLogger->warning(
                DamLogger::NAMESPACE_EXT_SYSTEM_CALLBACK,
                sprintf('Single use file %s used without a holder', (string) $resolution->getFile()->getId()),
            );
        }

        if ($this->singleUseEnforced) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_SINGLE_USE_HOLDER_REQUIRED);
        }

        // Used, but claimed for nobody. Without a check date the group would never reach the reconcile, so
        // the holder the ext system does know about could never be written back.
        foreach ($singleUseResolutions as $resolution) {
            $resolution->getFile()->getAssetAttributes()->setUsedByCheckAfter(AssetFileManager::nextUsageCheck());
        }
        $this->assetFileManager->flush();
    }

    /**
     * @param list<ImageUseResolution> $singleUseResolutions
     * @param array<string, list<AssetFile>> $groupByRoot
     *
     * @return list<ImageUsageConflictDto>
     */
    private function collectConflicts(
        array $singleUseResolutions,
        array $groupByRoot,
        UsageClaim $claim,
        ?UsageClaim $handOver,
    ): array {
        $conflicts = [];
        foreach ($singleUseResolutions as $resolution) {
            $file = $resolution->getFile();
            foreach ($groupByRoot[$file->getTakeOverRootId()] ?? [] as $groupFile) {
                $attributes = $groupFile->getAssetAttributes();
                if (App::EMPTY_STRING === $attributes->getUsedByHolderName()
                    || $claim->matches($attributes)
                    || $handOver?->matches($attributes)
                ) {
                    continue;
                }

                $conflicts[] = ImageUsageConflictDto::getInstance((string) $file->getId(), $attributes);

                break;
            }
        }

        return $conflicts;
    }

    /**
     * @throws ForbiddenOperationException
     */
    private function assertUsableSource(ImageFile $source): void
    {
        // A duplicate has no file of its own and a side slot is not what the caller references, so neither
        // can be taken over — the copy would end up on a different file than the one that was asked for.
        if (
            false === $source->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)
            || $source->getId() !== $source->getAsset()->getMainFile()?->getId()
        ) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_SOURCE_INVALID);
        }
    }
}
