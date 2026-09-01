<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use League\Flysystem\FilesystemException;

final class AssetFileRouteStorageManager extends AbstractManager
{
    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly FfmpegService $ffmpegService,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws FfmpegException
     */
    public function writeRouteFile(AssetFile $assetFile, AssetFileRoute $route): void
    {
        $publicFilesystem = $this->fileSystemProvider->getPublicFilesystem($assetFile);
        $contents = $this->createRouteFileStream($assetFile);

        if ($publicFilesystem->has($route->getUri()->getPath())) {
            $publicFilesystem->delete($route->getUri()->getPath());
        }

        $publicFilesystem->writeStream(
            location: $route->getUri()->getPath(),
            contents: $contents
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

    /**
     * @return resource
     *
     * @throws FilesystemException
     * @throws FfmpegException
     */
    private function createRouteFileStream(AssetFile $assetFile)
    {
        $sourceFilesystem = $this->fileSystemProvider->getFilesystemByStorable($assetFile);
        $filePath = $assetFile->getAssetAttributes()->getFilePath();

        if (AudioMimeTypes::requiresPublicMp3Conversion($assetFile->getAssetAttributes()->getMimeType())) {
            return $this->ffmpegService->transcodeToMp3(
                source: $this->fileSystemProvider->getTmpFileSystem()->writeTmpFileFromFilesystem($sourceFilesystem, $filePath),
                bitrateKbps: AudioMimeTypes::PUBLIC_CONVERSION_BITRATE_KBPS,
            )->readStream();
        }

        return $sourceFilesystem->readStream($filePath);
    }
}
