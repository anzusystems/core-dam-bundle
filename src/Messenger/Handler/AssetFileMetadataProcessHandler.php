<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\MetadataProcessor;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetFileMetadataProcessMessage;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ChunkRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AssetFileMetadataProcessHandler
{
    public function __construct(
        private readonly AssetFileRepository $assetFileRepository,
        private readonly MetadataProcessor $metadataProcessor,
        private readonly ChunkRepository $chunkRepository,
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws FilesystemException
     * @throws SerializerException
     */
    public function __invoke(AssetFileMetadataProcessMessage $message): void
    {
        $assetFile = $this->assetFileRepository->find($message->getAssetId());
        $chunk = $this->chunkRepository->find($message->getChunkId());

        if (null === $assetFile || null === $chunk) {
            return;
        }

        $file = $this->fileSystemProvider->getTmpFileSystem()->writeTmpFileFromFilesystem(
            $this->fileSystemProvider->getFilesystemByStorable($chunk),
            $chunk->getFilePath()
        );

        $this->metadataProcessor->process($assetFile, $file);
    }
}
