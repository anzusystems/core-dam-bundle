<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetPropertiesRefresher;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetChangedEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\ImageUseResolution;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\AssetFileCopyResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultListDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Throwable;

/**
 * Decides whether each image of a batch may be used as it is, or has to be taken over into the caller's
 * licence first. The licence flags own the take-over rule, so the caller never
 * decides it, it only receives the files to use.
 *
 * The whole batch runs under one transaction. Unlike {@see ImageCopyFacade::prepareCopyList()} every copy is
 * finished inside the request: the caller stores the returned ids immediately, so a target still being filled
 * asynchronously would be referenced before it has any data.
 */
final class ImageUseFacade
{
    use IndexManagerAwareTrait;
    use ValidatorAwareTrait;

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private readonly ImageCopyFacade $imageCopyFacade,
        private readonly AssetManager $assetManager,
        private readonly AssetPropertiesRefresher $assetPropertiesRefresher,
        private readonly AssetFileManager $assetFileManager,
        private readonly AccessDenier $accessDenier,
        private readonly AssetChangedEventDispatcher $assetChangedEventDispatcher,
    ) {
    }

    /**
     * @throws ForbiddenOperationException
     * @throws ValidationException
     * @throws Throwable
     */
    public function useImages(ImageUseRequestDto $dto): ImageUseResultListDto
    {
        $this->validator->validate($dto);
        $this->denyUnlessAllowed($dto);

        try {
            $this->assetFileManager->beginTransaction();
            $resolved = [];
            foreach ($dto->getItems() as $item) {
                $resolved[] = $this->resolveItem($item);
            }
            $results = new ArrayCollection();
            foreach ($resolved as $resolution) {
                $results->add(ImageUseResultDto::getInstance($resolution->getFile()));
            }
            $result = ImageUseResultListDto::getInstance($results);
            $this->assetFileManager->commit();
        } catch (Throwable $exception) {
            // Only the DB is rolled back. Bytes already written to the bucket are not transactional
            // and remain there as orphans.
            if ($this->assetFileManager->isTransactionActive()) {
                $this->assetFileManager->rollback();
            }

            throw $exception;
        }

        $targetAssets = $this->takenOverTargetAssets($resolved);
        if ([] !== $targetAssets) {
            $this->indexManager->indexBulk($targetAssets);
            $this->assetChangedEventDispatcher->dispatchAssetChangedEvent(new ArrayCollection($targetAssets));
        }

        return $result;
    }

    /**
     * @throws ForbiddenOperationException
     */
    private function denyUnlessAllowed(ImageUseRequestDto $dto): void
    {
        $sourceLicences = [];
        $targetLicences = [];
        foreach ($dto->getItems() as $item) {
            $sourceLicence = $item->getImageFile()->getLicence();
            $sourceLicences[(int) $sourceLicence->getId()] = $sourceLicence;
            $targetLicence = $item->getTargetAssetLicence();
            if ($targetLicence instanceof AssetLicence && $targetLicence->isNot($sourceLicence)) {
                $targetLicences[(int) $targetLicence->getId()] = $targetLicence;
            }
        }

        foreach ($sourceLicences as $licence) {
            $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_ASSET_READ, $licence);
        }
        foreach ($targetLicences as $licence) {
            $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $licence);
        }
    }

    /**
     * @param list<ImageUseResolution> $resolved
     *
     * @return list<Asset>
     */
    private function takenOverTargetAssets(array $resolved): array
    {
        $targetAssets = [];
        foreach ($resolved as $resolution) {
            if (false === $resolution->isTakenOver()) {
                continue;
            }

            $targetAsset = $resolution->getFile()->getAsset();
            $targetAssets[(string) $targetAsset->getId()] = $targetAsset;
        }

        return array_values($targetAssets);
    }

    /**
     * @throws ForbiddenOperationException
     * @throws Throwable
     */
    private function resolveItem(ImageUseItemDto $item): ImageUseResolution
    {
        $source = $item->getImageFile();
        $this->guardUsableSource($source);

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
        if ($directUseAllowed && false === $item->isForceTakeOver()) {
            return ImageUseResolution::directUse($source);
        }
        if (false === $targetLicence instanceof AssetLicence) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_DIRECT_USE_DISABLED);
        }

        $sameLicence = $source->getLicence()->is($targetLicence);
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

        return $this->finishCopy($this->imageCopyFacade->prepareTakeOver($copyDto), $source);
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
            return ImageUseResolution::takenOver($targetMainFile, $source);
        }

        if (false === $prepared->getResult()->is(AssetFileCopyStatus::Copy)) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT);
        }

        $this->imageCopyFacade->copyAssetSlots($source->getAsset(), $targetAsset);
        $this->assetPropertiesRefresher->refreshProperties($targetAsset);
        $this->assetManager->updateExisting(asset: $targetAsset, trackModification: false);

        return ImageUseResolution::takenOver($targetMainFile, $source);
    }

    /**
     * @throws ForbiddenOperationException
     */
    private function guardUsableSource(ImageFile $source): void
    {
        // A duplicate has no file of its own and a side slot is not what the caller references, so neither
        // can be taken over.
        if (
            false === $source->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)
            || $source->getId() !== $source->getAsset()->getMainFile()?->getId()
        ) {
            throw new ForbiddenOperationException(ForbiddenOperationException::IMAGE_TAKE_OVER_SOURCE_INVALID);
        }
    }
}
