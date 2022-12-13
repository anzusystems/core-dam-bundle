<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Chunk;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ChunkFileManager
{
    public function __construct(
        private readonly NameGenerator $nameGenerator,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly FirstChunkProcessor $firstChunkProcessor,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function saveChunk(Chunk $chunk, UploadedFile $file): void
    {
        $path = $this->nameGenerator->generatePath();
        $fileSystem = $this->fileSystemProvider->getFilesystemByStorable($chunk);

        $chunk
            ->setMimeType((string) $file->getMimeType())
            ->setFilePath($path->getRelativePath())
        ;

        if (App::ZERO === $chunk->getOffset()) {
            $this->firstChunkProcessor->process($chunk, $file);
        }

        $stream = fopen((string) $file->getRealPath(), 'rb+');
        $fileSystem->writeStream($path->getRelativePath(), $stream);
    }
}
