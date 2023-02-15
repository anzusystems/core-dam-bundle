<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropCache;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Event\ManipulatedImageEvent;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Repository\AbstractAssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Traits\EventDispatcherAwareTrait;
use RuntimeException;
use Throwable;

/**
 * @template-extends AssetFileFacade<ImageFile>
 */
final class ImageFacade extends AssetFileFacade
{
    use EventDispatcherAwareTrait;

    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly ImageFactory $imageFactory,
        private readonly ImageFileRepository $assetRepository,
        private readonly ImageRotator $imageRotator,
        private readonly FileStash $fileStash,
        private readonly CropCache $cropCache,
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

    protected function getManager(): AssetFileManager
    {
        return $this->imageManager;
    }

    protected function getFactory(): AssetFileFactory
    {
        return $this->imageFactory;
    }

    protected function getRepository(): AbstractAssetFileRepository
    {
        return $this->assetRepository;
    }

    private function createEvent(ImageFile $image): ManipulatedImageEvent
    {
        return $this->dispatcher->dispatch(new ManipulatedImageEvent(
            imageId: (string) $image->getId(),
            roiPositions: CollectionHelper::traversableToIds(
                traversable: $image->getRegionsOfInterest(),
                getIdAction: fn (RegionOfInterest $regionOfInterest): int => $regionOfInterest->getPosition()
            ),
            extSystem: $image->getExtSystem()->getSlug()
        ));
    }
}
