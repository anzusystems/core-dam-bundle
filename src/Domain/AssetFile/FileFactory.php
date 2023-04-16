<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use League\Flysystem\FilesystemException;

final readonly class FileFactory
{
    public function __construct(
        private FileSystemProvider $fileSystemProvider,
        private NameGenerator $nameGenerator,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function createFromChunks(AssetFile $assetFile): AdapterFile
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

        return new AdapterFile(
            path: $fileSystem->extendPath($path->getRelativePath()),
            adapterPath: $path->getRelativePath(),
            filesystem: $fileSystem
        );
    }
}
