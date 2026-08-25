<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\Crop;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\FileSystem\AbstractFilesystem;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCropDto;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToReadFile;

final readonly class CropCache
{
    public function __construct(
        private FileSystemProvider $fileSystemProvider,
        private NameGenerator $nameGenerator,
    ) {
    }

    public function tryGet(ImageFile $image, ImageCropDto $imageCrop): ?string
    {
        try {
            return $this->fileSystemProvider
                ->getCropFilesystemByImage($image)
                ->read($this->getPath($image, $imageCrop));
        } catch (UnableToReadFile) {
            return null;
        }
    }

    /**
     * @throws FilesystemException
     */
    public function store(ImageFile $image, ImageCropDto $imageCrop, string $content): void
    {
        $this->fileSystemProvider
            ->getCropFilesystemByImage($image)
            ->write(
                $this->getPath($image, $imageCrop),
                $content
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
    public function removeCacheByOriginFilePath(string $extSystemSlug, string $path, ?string $cropStorageName = null): void
    {
        $cacheDir = $this->getCacheDir($path);
        if (0 === strlen($cacheDir)) {
            return;
        }

        $this->resolveFilesystem($extSystemSlug, $cropStorageName)->deleteDirectory($cacheDir);
    }

    private function resolveFilesystem(string $extSystemSlug, ?string $cropStorageName): AbstractFilesystem
    {
        if (null === $cropStorageName) {
            return $this->fileSystemProvider->getCropFilesystemByExtSystemSlug($extSystemSlug);
        }

        $filesystem = $this->fileSystemProvider->getFileSystemByStorageName($cropStorageName);
        if (null === $filesystem) {
            throw new InvalidArgumentException("Unknown storage name ({$cropStorageName})");
        }

        return $filesystem;
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
