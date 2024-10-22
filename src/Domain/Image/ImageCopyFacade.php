<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetCopyBuilder;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFilePositionFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\AssetFileStorageOperator;
use AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize\ImageFileOptimalResizeCopyBuilder;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Messenger\Message\CopyAssetFileMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyResult;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final class ImageCopyFacade
{
    use MessageBusAwareTrait;

    public function __construct(
        private readonly ImageFileRepository $imageFileRepository,
        private readonly AssetCopyBuilder $assetCopyBuilder,
        private readonly EntityManagerInterface $entityManager,
        private readonly AssetFileStorageOperator $assetFileStorageOperator,
        private readonly ImageFileOptimalResizeCopyBuilder $imageFileOptimalResizeCopyBuilder,
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
                $resDto = $this->prepareCopyResult($imageCopyDto);
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
            if ($imageCopyResultDto->getResult()->is(AssetFileCopyResult::Copying) && $imageCopyResultDto->getFoundAsset()) {
                $this->messageBus->dispatch(new CopyAssetFileMessage(
                    $imageCopyResultDto->getAsset(),
                    $imageCopyResultDto->getFoundAsset()
                ));
            }
        }

        return new ArrayCollection($res);
    }

    private function prepareCopyResult(ImageCopyDto $copyDto): ImageCopyResultDto
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

            $foundAssets[(string) $foundAssetFile->getAsset()?->getId()] = $foundAssetFile->getAsset();
        }

        $firstFoundAsset = $foundAssets[(string) array_key_first($foundAssets)] ?? null;

        if (null === $firstFoundAsset) {
            $assetCopy = $this->assetCopyBuilder->copyDraft($copyDto->getAsset(), $copyDto->getTargetAssetLicence());

            return ImageCopyResultDto::create(
                asset: $copyDto->getAsset(),
                targetAssetLicence: $copyDto->getTargetAssetLicence(),
                result: AssetFileCopyResult::Copying,
                mainAssetFile: $assetCopy->getMainFile(),
                mainAsset: $assetCopy,
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
            mainAssetFile: $firstFoundAsset->getMainFile(),
            mainAsset: $firstFoundAsset,
        );
    }

    public function copyAsset(Asset $asset, Asset $copyAsset): void
    {
        // copy asset, asset metadata, slots, asset file,
        // setup new timetracking/user tracking fields
        dump('copying');

        foreach ($copyAsset->getSlots() as $targetSlot) {
            $assetSlot = $asset->getSlots()->findFirst(
                fn (int $index, AssetSlot $assetSlot) => $assetSlot->getName() === $targetSlot->getName()
            );

            if ($assetSlot instanceof AssetSlot) {
                $this->copyAssetSlot($assetSlot, $targetSlot);

                continue;
            }

            // todo failed ... not found!
        }

        // todo if copied dispatch message/mark as with file
    }

    private function copyAssetSlot(AssetSlot $assetSlot, AssetSlot $targetSlot): void
    {
        // todo
        $this->copyImageFile(
            $assetSlot->getAssetFile(),
            $targetSlot->getAssetFile()
        );
    }

    private function copyImageFile(ImageFile $imageFile, ImageFile $targetImageFile): void
    {
        dump($imageFile->getAssetAttributes()->getFilePath());
        $this->assetFileStorageOperator->copyToAssetFile($imageFile, $targetImageFile);

        foreach ($imageFile->getResizes() as $resize) {
            dump($resize->getFilePath());
            $this->imageFileOptimalResizeCopyBuilder->copyResizeToImage($resize, $targetImageFile);
            // todo resize
        }
    }
}
