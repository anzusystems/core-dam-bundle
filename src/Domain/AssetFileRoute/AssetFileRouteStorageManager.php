<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use League\Flysystem\FilesystemException;

final class AssetFileRouteStorageManager extends AbstractManager
{
    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function writeRouteFile(AssetFile $assetFile, AssetFileRoute $route): void
    {
        $publicFilesystem = $this->fileSystemProvider->getPublicFilesystem($assetFile);

        if ($publicFilesystem->has($route->getUri()->getPath())) {
            $publicFilesystem->delete($route->getUri()->getPath());
        }

        $publicFilesystem->writeStream(
            location: $route->getUri()->getPath(),
            contents: $this->fileSystemProvider->getFilesystemByStorable(
                storable: $assetFile
            )->readStream(
                location: $assetFile->getAssetAttributes()->getFilePath()
            )
        );
    }

    /**
     * @throws FilesystemException
     */
    public function deleteRouteFile(AssetFile $assetFile, string $path): void
    {
        $filesystem = $this->fileSystemProvider->getPublicFilesystem($assetFile);

        if ($filesystem->has($path)) {
            $filesystem->delete($path);
        }
    }
}
