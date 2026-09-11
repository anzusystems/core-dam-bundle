<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetPropertiesRefresher;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\AssetFileCopyResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTakeOverRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTakeOverResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Decides whether an image may be used as it is, or has to be taken over into the caller's licence first.
 * The licence flags own that rule, so the caller never decides — it only receives the file to use.
 *
 * Unlike {@see ImageCopyFacade::prepareCopyList()} the copy is finished inside the request: the caller
 * stores the returned id immediately, so a target that is still being filled asynchronously would be
 * referenced before it has any data.
 */
final class ImageTakeOverFacade
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private readonly ImageCopyFacade $imageCopyFacade,
        private readonly AssetManager $assetManager,
        private readonly AssetPropertiesRefresher $assetPropertiesRefresher,
        private readonly AssetFileManager $assetFileManager,
        private readonly AssetFileRepository $assetFileRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ForbiddenOperationException
     * @throws Throwable
     */
    public function takeOver(ImageTakeOverRequestDto $dto): ImageTakeOverResultDto
    {
        $this->validator->validate($dto);

        $source = $dto->getImageFile();
        $this->assertUsableSource($source);
        $targetLicence = $dto->getTargetAssetLicence();

        $directUseAllowed = $source->getLicence()->getFlags()->isDirectUseAllowed();

        // The source licence flag decides first: a caller that mislabels the licence in its request must not
        // be able to skip the copy.
        if ($directUseAllowed && false === $dto->isForce()) {
            return ImageTakeOverResultDto::getInstance($source, takenOver: false);
        }
        if (false === $targetLicence instanceof AssetLicence) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_DIRECT_USE_DISABLED);
        }

        $sameLicence = $source->getLicence()->getId() === $targetLicence->getId();

        // Asking for the licence the file already lives in leaves nothing to copy, so the source is handed
        // back — but only where using it there is allowed at all. Without the flag the same request is
        // exactly the direct use the licence forbids, no matter which licence the caller names as target.
        if ($sameLicence && $directUseAllowed) {
            return ImageTakeOverResultDto::getInstance($source, takenOver: false);
        }
        if ($sameLicence) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_DIRECT_USE_DISABLED);
        }
        if ($targetLicence->getFlags()->isNotManualUploadAllowed()) {
            throw new ForbiddenOperationException(ForbiddenOperationException::LICENCE_MANUAL_UPLOAD_DISABLED);
        }

        return $this->takeOverToLicence($source, $targetLicence);
    }

    /**
     * @throws Throwable
     */
    private function takeOverToLicence(ImageFile $source, AssetLicence $targetLicence): ImageTakeOverResultDto
    {
        $copyDto = (new ImageCopyDto())
            ->setAsset($source->getAsset())
            ->setTargetAssetLicence($targetLicence);

        try {
            $this->entityManager->beginTransaction();
            $result = $this->finishCopy($this->imageCopyFacade->prepareCopy($copyDto), $source);
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
    private function finishCopy(AssetFileCopyResultDto $prepared, ImageFile $source): ImageTakeOverResultDto
    {
        $targetAsset = $prepared->getTargetAsset();
        $targetMainFile = $prepared->getTargetMainFile();
        if (false === $targetAsset instanceof Asset || false === $targetMainFile instanceof AssetFile) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT);
        }

        if ($prepared->getResult()->is(AssetFileCopyStatus::Exists)) {
            // The identical file already lives in the target licence. Reusing it is only safe while nothing
            // licence bound is lost: a single use source would have to move its exclusivity onto a file other
            // articles may already hold, so it is refused instead of silently relaxed.
            if ($source->getFlags()->isSingleUse()) {
                throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT);
            }

            return $this->reuseExisting($targetMainFile, $source);
        }

        if (false === $prepared->getResult()->is(AssetFileCopyStatus::Copy)) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT);
        }

        $this->imageCopyFacade->copyAssetSlots($source->getAsset(), $targetAsset);
        $this->assetPropertiesRefresher->refreshProperties($targetAsset);
        $this->assetManager->updateExisting(asset: $targetAsset, trackModification: false);
        $this->indexManager->index($targetAsset);

        return ImageTakeOverResultDto::getInstance($targetMainFile, takenOver: true);
    }

    /**
     * A file found by checksum did not necessarily get into the target licence by a take over — the same bytes
     * uploaded manually land here too, with no root of their own. Such a file is adopted under the source root,
     * otherwise the caller would store a photo with no link to the original: the exclusivity group would fall
     * apart and the licence clock of the original would never start.
     *
     * A file something else was already taken over from keeps its own root. Adopting it would merge two groups
     * into one and leave its own copies pointing at a root their source no longer belongs to.
     *
     * @throws ForbiddenOperationException
     */
    private function reuseExisting(AssetFile $existing, ImageFile $source): ImageTakeOverResultDto
    {
        if (App::EMPTY_STRING !== $existing->getAssetAttributes()->getTakenOverFromId()) {
            return ImageTakeOverResultDto::getInstance($existing, takenOver: true);
        }
        if ($this->assetFileRepository->existsTakenOverFrom((string) $existing->getId())) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT);
        }

        $existing->getAssetAttributes()->setTakenOverFromId($source->getTakeOverRootId());
        // Flushed here, inside the transaction opened by takeOverToLicence(): committing it does not flush the
        // unit of work, and unlike the copy branch nothing else in this path writes.
        $this->assetFileManager->updateExisting(assetFile: $existing, trackModification: false);

        return ImageTakeOverResultDto::getInstance($existing, takenOver: true);
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
