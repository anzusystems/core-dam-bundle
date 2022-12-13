<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;
use League\Flysystem\FilesystemException;

final class FileFactory
{
    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly NameGenerator $nameGenerator,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function createFromChunks(AssetFile $assetFile): File
    {
        $path = $this->nameGenerator->generatePath();
        $fileSystem = $this->fileSystemProvider->getTmpFileSystem();

        $firstChunk = $assetFile->getChunks()->first();
        if (false === $firstChunk instanceof Chunk) {
            throw new RuntimeException("Asset file ({$assetFile->getId()}) has no chunks uploaded");
        }

        $chunkFileSystem = $this->fileSystemProvider->getFilesystemByStorable($firstChunk);
        foreach ($assetFile->getChunks() as $chunk) {
            $fileSystem->appendTmpStream(
                $path->getRelativePath(),
                $chunkFileSystem->read($chunk->getFilePath())
            );
        }

        return new File(
            path: $fileSystem->extendPath($path->getRelativePath()),
            adapterPath: $path->getRelativePath(),
            filesystem: $fileSystem
        );
    }
}
