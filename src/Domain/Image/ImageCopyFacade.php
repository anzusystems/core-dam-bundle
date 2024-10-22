<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetCopyBuilder;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetPropertiesRefresher;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileCopyBuilder;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\AssetFileStorageOperator;
use AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize\ImageFileOptimalResizeCopyBuilder;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Messenger\Message\CopyAssetFileMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyResult;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
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

    public function __construct(
        private readonly ImageFileRepository $imageFileRepository,
        private readonly AssetCopyBuilder $assetCopyBuilder,
        private readonly EntityManagerInterface $entityManager,
        private readonly AssetFileStorageOperator $assetFileStorageOperator,
        private readonly ImageFileOptimalResizeCopyBuilder $imageFileOptimalResizeCopyBuilder,
        private readonly AssetFileCopyBuilder $assetFileCopyBuilder,
        private readonly AssetPropertiesRefresher $refresher,
        private readonly AssetManager $assetManager,
    ) {
    }

    /**
     * @param Collection<int, ImageCopyDto> $collection
     * @throws Throwable
     */
    public function copyList(Collection $collection): Collection
    {
        $res = [];

        try {
            $this->entityManager->beginTransaction();

            foreach ($collection as $imageCopyDto) {
                $resDto = $this->prepareCopy($imageCopyDto);
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
            if ($imageCopyResultDto->getResult()->is(AssetFileCopyResult::Copying) && $imageCopyResultDto->getTargetAsset()) {
                $this->messageBus->dispatch(new CopyAssetFileMessage(
                    $imageCopyResultDto->getAsset(),
                    $imageCopyResultDto->getTargetAsset()
                ));
            }
        }

        return new ArrayCollection($res);
    }

    public function copyAssetFiles(Asset $asset, Asset $copyAsset): void
    {
        try {
            $this->entityManager->beginTransaction();

            $this->copyAssetSlots($asset, $copyAsset);
            $this->assetManager->updateExisting(asset: $copyAsset, trackModification: false);
            $this->indexManager->index($asset);

            $this->entityManager->commit();
        } catch (Throwable $exception) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $exception;
        }

        // todo if copied dispatch message/mark as with file
    }

    private function prepareCopy(ImageCopyDto $copyDto): ImageCopyResultDto
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

            return ImageCopyResultDto::create(
                asset: $copyDto->getAsset(),
                targetAssetLicence: $copyDto->getTargetAssetLicence(),
                result: AssetFileCopyResult::Copying,
                targetMainFile: $assetCopy->getMainFile(),
                targetAsset: $assetCopy,
            );
        }

        if (count($foundAssets) > 1 || false === $firstFoundAsset->hasSameFilesIdentityString($copyDto->getAsset())) {
            return ImageCopyResultDto::create(
                asset: $copyDto->getAsset(),
                targetAssetLicence: $copyDto->getTargetAssetLicence(),
                result: AssetFileCopyResult::NotAllowed,
                assetConflicts: array_values($foundAssets)
            );
        }

        return ImageCopyResultDto::create(
            asset: $copyDto->getAsset(),
            targetAssetLicence: $copyDto->getTargetAssetLicence(),
            result: AssetFileCopyResult::Exists,
            targetMainFile: $firstFoundAsset->getMainFile(),
            targetAsset: $firstFoundAsset,
        );
    }

    private function copyAssetSlots(Asset $asset, Asset $copyAsset): void
    {
        foreach ($copyAsset->getSlots() as $targetSlot) {
            $assetSlot = $asset->getSlots()->findFirst(
                fn (int $index, AssetSlot $assetSlot) => $assetSlot->getName() === $targetSlot->getName()
            );

            if ($assetSlot instanceof AssetSlot) {
                $this->assetFileCopyBuilder->copy($assetSlot->getAssetFile(), $targetSlot->getAssetFile());

                continue;
            }

            // todo failed ... not found!
        }
    }
}
