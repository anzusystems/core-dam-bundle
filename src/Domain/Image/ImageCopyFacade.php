<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetCopyBuilder;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileCopyBuilder;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetChangedEventDispatcher;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Messenger\Message\CopyAssetFileMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\AssetFileCopyResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final class ImageCopyFacade
{
    use MessageBusAwareTrait;
    use IndexManagerAwareTrait;
    use ValidatorAwareTrait;

    private const int BULK_COPY_SIZE = 20;

    /**
     * @param AssetFileManager<AssetFile> $assetFileManager
     */
    public function __construct(
        private readonly ImageFileRepository $imageFileRepository,
        private readonly AssetCopyBuilder $assetCopyBuilder,
        private readonly EntityManagerInterface $entityManager,
        private readonly AssetFileCopyBuilder $assetFileCopyBuilder,
        private readonly AssetManager $assetManager,
        private readonly AssetFileEventDispatcher $assetFileEventDispatcher,
        private readonly AssetFileStatusManager $assetFileStatusManager,
        private readonly AccessDenier $accessDenier,
        private readonly AssetFileManager $assetFileManager,
        private readonly AssetFileRepository $assetFileRepository,
        private readonly AssetChangedEventDispatcher $assetChangedEventDispatcher,
    ) {
    }

    /**
     * @param Collection<int, ImageCopyDto> $collection
     * @return Collection<int, AssetFileCopyResultDto>
     *
     * @throws Throwable
     */
    public function prepareCopyList(Collection $collection): Collection
    {
        $this->assertManualUploadAllowed($collection);
        $this->validateMaxBulkCount($collection);
        $this->validator->validate($collection);

        /** @var AssetFileCopyResultDto[] $res */
        $res = [];

        try {
            $this->entityManager->beginTransaction();

            foreach ($collection as $imageCopyDto) {
                $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_ASSET_READ, $imageCopyDto->getAsset()->getLicence());
                $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $imageCopyDto->getTargetAssetLicence());

                $resDto = $this->prepareTakeOver($imageCopyDto);
                $res[] = $resDto;
            }

            $this->entityManager->commit();
        } catch (Throwable $exception) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $exception;
        }

        foreach ($res as $imageCopyResultDto) {
            if ($imageCopyResultDto->getResult()->is(AssetFileCopyStatus::Copy) && $imageCopyResultDto->getTargetAsset()) {
                $this->messageBus->dispatch(new CopyAssetFileMessage(
                    $imageCopyResultDto->getAsset(),
                    $imageCopyResultDto->getTargetAsset()
                ));
            }
        }

        $this->notifyExisting($res);

        return new ArrayCollection($res);
    }

    /**
     * A file found by checksum did not necessarily get into the target licence by a take over, the same bytes
     * uploaded manually land here too, with no root of their own. Such a file is adopted under the source root,
     * otherwise the caller would store a photo with no link to the original, and takes the single use flag of the
     * source. A file of another group is refused: adopting it would merge two groups.
     *
     * @throws Throwable
     */
    public function prepareTakeOver(ImageCopyDto $copyDto): AssetFileCopyResultDto
    {
        $prepared = $this->prepareCopy($copyDto);
        $existing = $prepared->getTargetMainFile();
        $source = $copyDto->getAsset()->getMainFile();
        if (
            $prepared->getResult()->isNot(AssetFileCopyStatus::Exists)
            || null === $existing
            || null === $source
            || $this->adopt($existing, $source)
        ) {
            return $prepared;
        }

        return AssetFileCopyResultDto::create(
            asset: $copyDto->getAsset(),
            targetAssetLicence: $copyDto->getTargetAssetLicence(),
            result: AssetFileCopyStatus::NotAllowed,
            assetConflicts: [$existing->getAsset()],
        );
    }

    /**
     * @throws Throwable
     */
    public function copyAssetFiles(Asset $asset, Asset $copyAsset, bool $copyTrackingFields = false): void
    {
        try {
            $this->entityManager->beginTransaction();
            $this->copyAssetSlots($asset, $copyAsset);
            $this->assetManager->updateExisting(asset: $copyAsset, trackModification: false);
            if ($copyTrackingFields) {
                $this->copyTrackingFieldsToAsset($asset, $copyAsset);
            }
            $this->indexManager->index($copyAsset);
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $exception;
        }

        foreach ($copyAsset->getSlots() as $slot) {
            $this->assetFileEventDispatcher->dispatchAssetFileCopiedEvent($slot->getAssetFile());
        }
    }

    public function prepareCopy(ImageCopyDto $copyDto): AssetFileCopyResultDto
    {
        /** @var array<string, Asset> $foundAssets */
        $foundAssets = [];
        foreach ($copyDto->getAsset()->getSlots() as $slot) {
            $foundAssetFile = $this->imageFileRepository->findProcessedByChecksumAndLicence(
                checksum: $slot->getAssetFile()->getAssetAttributes()->getChecksum(),
                licence: $copyDto->getTargetAssetLicence()
            );

            if (null === $foundAssetFile) {
                continue;
            }

            $foundAssets[(string) $foundAssetFile->getAsset()->getId()] = $foundAssetFile->getAsset();
        }

        $firstFoundAsset = $foundAssets[(string) array_key_first($foundAssets)] ?? null;

        if (null === $firstFoundAsset) {
            $assetCopy = $this->assetCopyBuilder->buildDraftAssetCopy($copyDto->getAsset(), $copyDto->getTargetAssetLicence());

            return AssetFileCopyResultDto::create(
                asset: $copyDto->getAsset(),
                targetAssetLicence: $copyDto->getTargetAssetLicence(),
                result: AssetFileCopyStatus::Copy,
                targetMainFile: $assetCopy->getMainFile(),
                targetAsset: $assetCopy,
            );
        }

        if (count($foundAssets) > 1 || false === $firstFoundAsset->hasSameFilesIdentityString($copyDto->getAsset())) {
            return AssetFileCopyResultDto::create(
                asset: $copyDto->getAsset(),
                targetAssetLicence: $copyDto->getTargetAssetLicence(),
                result: AssetFileCopyStatus::NotAllowed,
                assetConflicts: array_values($foundAssets)
            );
        }

        return AssetFileCopyResultDto::create(
            asset: $copyDto->getAsset(),
            targetAssetLicence: $copyDto->getTargetAssetLicence(),
            result: AssetFileCopyStatus::Exists,
            targetMainFile: $firstFoundAsset->getMainFile(),
            targetAsset: $firstFoundAsset,
        );
    }

    public function copyAssetSlots(Asset $asset, Asset $copyAsset): void
    {
        foreach ($copyAsset->getSlots() as $targetSlot) {
            $assetSlot = $asset->getSlots()->findFirst(
                fn (int $index, AssetSlot $assetSlot) => $assetSlot->getName() === $targetSlot->getName()
            );

            if ($assetSlot instanceof AssetSlot) {
                $this->assetFileCopyBuilder->copy($assetSlot->getAssetFile(), $targetSlot->getAssetFile());

                continue;
            }

            $this->assetFileStatusManager->toFailed(
                $targetSlot->getAssetFile(),
                AssetFileFailedType::Unknown
            );
        }
    }

    private function adopt(AssetFile $existing, AssetFile $source): bool
    {
        $rootId = $source->getTakeOverRootId();
        $existingId = (string) $existing->getId();
        $attributes = $existing->getAssetAttributes();
        $changed = false;
        if ($existingId !== $rootId && $attributes->getTakenOverFromId() !== $rootId) {
            if ($attributes->isTakenOver() || $this->assetFileRepository->existsTakenOverFrom($existingId)) {
                return false;
            }
            $attributes->setTakenOverFromId($rootId);
            $changed = true;
        }
        if ($source->getFlags()->isSingleUse() && false === $existing->getFlags()->isSingleUse()) {
            $existing->getFlags()->setSingleUse(true);
            $changed = true;
        }
        if ($changed) {
            $this->assetFileManager->updateExisting(assetFile: $existing, trackModification: false);
        }

        return true;
    }

    /**
     * @param list<AssetFileCopyResultDto> $results
     */
    private function notifyExisting(array $results): void
    {
        $assets = [];
        foreach ($results as $result) {
            $asset = $result->getTargetAsset();
            if ($result->getResult()->is(AssetFileCopyStatus::Exists) && $asset instanceof Asset) {
                $assets[(string) $asset->getId()] = $asset;
            }
        }
        if ([] === $assets) {
            return;
        }

        $this->indexManager->indexBulk(array_values($assets));
        $this->assetChangedEventDispatcher->dispatchAssetChangedEvent(new ArrayCollection(array_values($assets)));
    }

    // Adm-only entrypoint — sys copy-job flows through JobImageCopyFacade, not here.
    /**
     * @param Collection<int, ImageCopyDto> $collection
     */
    private function assertManualUploadAllowed(Collection $collection): void
    {
        foreach ($collection as $imageCopyDto) {
            if ($imageCopyDto->getTargetAssetLicence()->getFlags()->isNotManualUploadAllowed()) {
                throw new ForbiddenOperationException(ForbiddenOperationException::LICENCE_MANUAL_UPLOAD_DISABLED);
            }
        }
    }

    /**
     * @param Collection<int, ImageCopyDto> $dtoList
     */
    private function validateMaxBulkCount(Collection $dtoList): void
    {
        if ($dtoList->count() > self::BULK_COPY_SIZE) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_BULK_SIZE_EXCEEDED);
        }
    }

    private function copyTrackingFieldsToAsset(Asset $fromAsset, Asset $toAsset): void
    {
        $this->copyTrackingFields($fromAsset, $toAsset);
        $this->copyTrackingFields($fromAsset->getMetadata(), $toAsset->getMetadata());

        foreach ($fromAsset->getSlots() as $fromSlot) {
            $targetSlot = $toAsset->getSlots()->findFirst(
                fn (int $index, AssetSlot $assetSlot) => $assetSlot->getName() === $fromSlot->getName()
            );
            if (false === $targetSlot instanceof AssetSlot) {
                continue;
            }

            $this->copyTrackingFields($fromSlot, $targetSlot);
            $this->copyTrackingFields($fromSlot->getAssetFile(), $targetSlot->getAssetFile());
            $this->copyTrackingFields($fromSlot->getAssetFile()->getMetadata(), $targetSlot->getAssetFile()->getMetadata());
        }
    }

    private function copyTrackingFields(
        UserTrackingInterface | TimeTrackingInterface $fromEntity,
        UserTrackingInterface | TimeTrackingInterface $toEntity,
    ): void {
        if ($fromEntity instanceof TimeTrackingInterface && $toEntity instanceof TimeTrackingInterface) {
            $toEntity
                ->setCreatedAt($fromEntity->getCreatedAt())
                ->setModifiedAt($fromEntity->getModifiedAt())
            ;
        }
        if ($fromEntity instanceof UserTrackingInterface && $toEntity instanceof UserTrackingInterface) {
            $toEntity
                ->setCreatedBy($fromEntity->getCreatedBy())
                ->setModifiedBy($fromEntity->getModifiedBy())
            ;
        }
    }
}
