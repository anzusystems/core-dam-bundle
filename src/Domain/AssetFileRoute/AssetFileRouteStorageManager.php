<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileRoute\AssetFilePublicRouteAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioPublicationAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use Google\Service\Compute\Route;
use League\Flysystem\FilesystemException;
use Symfony\Component\String\Slugger\SluggerInterface;

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

        if ($publicFilesystem->has($route->getPath())) {
            $publicFilesystem->delete($route->getPath());
        }

        $publicFilesystem->writeStream(
            location: $route->getPath(),
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
