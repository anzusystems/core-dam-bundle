<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\Crop;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCropDto;
use League\Flysystem\FilesystemException;

final readonly class CropCache
{
    public function __construct(
        private FileSystemProvider $fileSystemProvider,
        private NameGenerator $nameGenerator,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function isStored(ImageFile $image, ImageCropDto $imageCrop): bool
    {
        return $this->fileSystemProvider
            ->getCropFilesystemByExtSystemSlug($image->getExtSystem()->getSlug())
            ->fileExists($this->getPath($image, $imageCrop));
    }

    /**
     * @throws FilesystemException
     */
    public function store(ImageFile $image, ImageCropDto $imageCrop, string $content): void
    {
        $this->fileSystemProvider
            ->getCropFilesystemByExtSystemSlug($image->getExtSystem()->getSlug())
            ->write(
                $this->getPath($image, $imageCrop),
                $content
            );
    }

    /**
     * @throws FilesystemException
     */
    public function get(ImageFile $image, ImageCropDto $imageCrop): string
    {
        return $this->fileSystemProvider
            ->getCropFilesystemByExtSystemSlug($image->getExtSystem()->getSlug())
            ->read(
                $this->getPath($image, $imageCrop),
            );
    }

    /**
     * @throws FilesystemException
     */
    public function removeCache(ImageFile $image): void
    {
        $this->removeCacheByOriginFilePath(
            $image->getExtSystem()->getSlug(),
            $image->getAssetAttributes()->getFilePath()
        );
    }

    /**
     * @throws FilesystemException
     */
    public function removeCacheByOriginFilePath(string $extSystemSlug, string $path): void
    {
        $cacheDir = $this->getCacheDir($path);
        if (0 === strlen($cacheDir)) {
            return;
        }

        $this->fileSystemProvider
            ->getCropFilesystemByExtSystemSlug($extSystemSlug)
            ->deleteDirectory($cacheDir);
    }

    private function getCacheDir(string $path): string
    {
        return $this->nameGenerator->getPath($path)->getDir();
    }

    private function getPath(ImageFile $image, ImageCropDto $imageCrop): string
    {
        return $this->nameGenerator->alternatePath(
            $image->getAssetAttributes()->getFilePath(),
            (string) $imageCrop
        )->getRelativePath();
    }
}
