<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use League\Flysystem\FilesystemException;

final readonly class FileFactory
{
    public function __construct(
        private FileSystemProvider $fileSystemProvider,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function createFromChunks(AssetFile $assetFile): AdapterFile
    {
        $fileSystem = $this->fileSystemProvider->getTmpFileSystem();
        $path = $fileSystem->getTmpFileName();

        $firstChunk = $assetFile->getChunks()->first();
        if (false === $firstChunk instanceof Chunk) {
            throw new RuntimeException("Asset file ({$assetFile->getId()}) has no chunks uploaded");
        }

        $chunkFileSystem = $this->fileSystemProvider->getFilesystemByStorable($firstChunk);
        foreach ($assetFile->getChunks() as $chunk) {
            $fileSystem->appendTmpStream(
                $path,
                $chunkFileSystem->read($chunk->getFilePath())
            );
        }

        return new AdapterFile(
            path: $fileSystem->extendPath($path),
            adapterPath: $path,
            filesystem: $fileSystem
        );
    }

    /**
     * @throws FilesystemException
     */
    public function createFromStorage(AssetFile $assetFile): AdapterFile
    {
        $originStorage = $assetFile->getAssetAttributes()->getOriginStorage();
        if (null === $originStorage) {
            throw new RuntimeException("Asset file ({$assetFile->getId()}) has no defined originStorage");
        }

        $fileSystem = $this->fileSystemProvider->getFileSystemByStorageName($originStorage->getStorageName());
        if (null === $fileSystem) {
            throw new RuntimeException(
                sprintf(
                    'File system not configured (%s), while creating file (%s)',
                    $originStorage->getStorageName(),
                    $assetFile->getId()
                )
            );
        }

        if (false === $fileSystem->has($originStorage->getPath())) {
            throw new DomainException(
                sprintf(
                    'File (%s) not exists in storage (%s)',
                    $originStorage->getStorageName(),
                    $originStorage->getPath()
                )
            );
        }

        $tmpFileSystem = $this->fileSystemProvider->getTmpFileSystem();

        return AdapterFile::createFromBaseFile(
            file: $tmpFileSystem->writeTmpFileFromFilesystem(
                filesystem: $fileSystem,
                filePath: $originStorage->getPath()
            ),
            filesystem: $tmpFileSystem
        );
    }
}
