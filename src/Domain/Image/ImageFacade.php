<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropCache;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\OptimalCropsProcessor;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Event\ManipulatedImageEvent;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Repository\AbstractAssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Traits\EventDispatcherAwareTrait;
use RuntimeException;
use Throwable;

/**
 * @template-extends AbstractAssetFileFacade<ImageFile>
 */
final class ImageFacade extends AbstractAssetFileFacade
{
    use EventDispatcherAwareTrait;

    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly ImageFactory $imageFactory,
        private readonly ImageFileRepository $assetRepository,
        private readonly ImageRotator $imageRotator,
        private readonly FileStash $fileStash,
        private readonly CropCache $cropCache,
        private readonly OptimalCropsProcessor $optimalCropsProcessor,
    ) {
    }

    /**
     * @throws RuntimeException
     */
    public function rotateImage(ImageFile $image, float $angle): ImageFile
    {
        try {
            $this->imageManager->beginTransaction();

            $event = $this->createEvent($image);
            $this->imageRotator->rotateImage($image, $angle);
            $this->imageManager->updateExisting($image);
            $this->indexManager->index($image->getAsset());
            $this->fileStash->emptyAll();
            $this->cropCache->removeCache($image);

            $this->imageManager->commit();

            $this->dispatcher->dispatch($event);
        } catch (Throwable $exception) {
            $this->imageManager->rollback();

            throw new RuntimeException('image_rotate_failed', 0, $exception);
        }

        return $image;
    }

    public function reprocessOptimalCrop(ImageFile $image): ImageFile
    {
        $this->imageManager->beginTransaction();

        try {
            $event = $this->createEvent($image);
            $this->optimalCropsProcessor->reprocess($image);
            $this->cropCache->removeCache($image);
            $this->imageManager->commit();

            $this->dispatcher->dispatch($event);
        } catch (Throwable $exception) {
            if ($this->imageManager->isTransactionActive()) {
                $this->imageManager->rollback();
            }

            throw new RuntimeException('image_process_optimal_crop_failed', 0, $exception);
        }

        return $image;
    }

    /**
     * @throws RuntimeException
     */
    public function update(ImageFile $image, ImageFileAdmDetailDto $dto): ImageFile
    {
        try {
            $this->imageManager->beginTransaction();

            $dispatchManipulatedImageEvent = $this->shouldDispatchManipulatedEvent($image, $dto);
            $this->imageManager->updateImage($image, $dto);
            $this->imageManager->updateExisting($image);
            $this->imageManager->commit();

            if ($dispatchManipulatedImageEvent) {
                $this->dispatcher->dispatch($this->createEvent($image));
            }
        } catch (Throwable $exception) {
            $this->imageManager->rollback();

            throw new RuntimeException('image_rotate_failed', 0, $exception);
        }

        return $image;
    }

    protected function getManager(): AssetFileManager
    {
        return $this->imageManager;
    }

    protected function getFactory(): AbstractAssetFileFactory
    {
        return $this->imageFactory;
    }

    protected function getRepository(): AbstractAssetFileRepository
    {
        return $this->assetRepository;
    }

    private function shouldDispatchManipulatedEvent(ImageFile $image, ImageFileAdmDetailDto $dto): bool
    {
        return false === ($image->getFlags()->isPublic() === $dto->getFlags()->isPublic());
    }

    private function createEvent(ImageFile $image): ManipulatedImageEvent
    {
        return new ManipulatedImageEvent(
            imageId: (string) $image->getId(),
            roiPositions: CollectionHelper::traversableToIds(
                traversable: $image->getRegionsOfInterest(),
                getIdAction: fn (RegionOfInterest $regionOfInterest): int => $regionOfInterest->getPosition()
            ),
            extSystem: $image->getExtSystem()->getSlug()
        );
    }
}
