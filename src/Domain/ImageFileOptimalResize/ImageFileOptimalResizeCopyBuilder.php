<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Controller\AbstractImageController;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFileOptimalResize;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use League\Flysystem\FilesystemException as FilesystemExceptionAlias;

final class ImageFileOptimalResizeCopyBuilder extends AbstractManager
{
    public function __construct(
        private readonly OptimalResizeFactory $optimalResizeFactory,
        private readonly OptimalResizeManager $optimalResizeManager,
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    public function copyResizeToImage(ImageFileOptimalResize $resize, ImageFile $targetImageFile): void
    {
        $targetPath = $this->optimalResizeFactory->createOptimalCropPath(
            $targetImageFile,
            $resize->getRequestedSize(),
            $targetImageFile->getImageAttributes()->getRotation()
        );

        $resizeCopy = $resize->__copy();
        $resizeCopy->setFilePath($targetPath);
        $resizeCopy->setImage($targetImageFile);
        $targetImageFile->getResizes()->add($resizeCopy);

        dump('New resize path: '.$targetPath);

        $this->fileSystemProvider->getFilesystemByStorable($resizeCopy)->writeStream(
            location: $targetPath,
            contents: $this->fileSystemProvider->getFilesystemByStorable($resize)->readStream($resize->getFilePath())
        );
    }
}
