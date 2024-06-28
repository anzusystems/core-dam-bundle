<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Exception\InvalidMimeTypeException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\CoreDamBundle\FileSystem\AbstractFilesystem;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\MimeGuesser;
use AnzuSystems\CoreDamBundle\Image\VispImageManipulator;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageMimeTypes;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginStorage;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;

final readonly class FileFactory
{
    public function __construct(
        private FileSystemProvider $fileSystemProvider,
        private VispImageManipulator $vispImageManipulator,
        private MimeGuesser $mimeGuesser,
        private Exiftool $exiftool
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
     * @throws ImageManipulatorException
     * @throws InvalidMimeTypeException
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

        if (
            $assetFile instanceof ImageFile &&
            false === empty($assetFile->getAssetAttributes()->getConvertToMime())
        ) {
            return $this->convertImage(
                fileSystem: $fileSystem,
                originStorage: $originStorage,
                convertToMime: $assetFile->getAssetAttributes()->getConvertToMime()
            );
        }
        $tmpFileSystem = $this->fileSystemProvider->getTmpFileSystem();

        $adapterFile = AdapterFile::createFromBaseFile(
            file: $tmpFileSystem->writeTmpFileFromFilesystem(
                filesystem: $fileSystem,
                filePath: $originStorage->getPath()
            ),
            filesystem: $tmpFileSystem
        );

        // if the png is uploaded using storage, additional metadata may have been added with the date,
        // so we need to delete it because the duplicity check could fail
        if ($this->mimeGuesser->guessMime($adapterFile->getRealPath()) === ImageMimeTypes::MimePng->value) {
            $this->exiftool->clearPng($adapterFile->getRealPath());
        }

        return $adapterFile;
    }

    /**
     * @throws FilesystemException
     * @throws ImageManipulatorException
     * @throws InvalidMimeTypeException
     */
    private function convertImage(
        AbstractFilesystem $fileSystem,
        OriginStorage $originStorage,
        string $convertToMime,
    ): AdapterFile {
        $tmpFileSystem = $this->fileSystemProvider->getTmpFileSystem();
        $tmpFile = $tmpFileSystem->writeTmpFileFromFilesystem(
            filesystem: $fileSystem,
            filePath: $originStorage->getPath()
        );

        $extension = $this->mimeGuesser->guessExtension($convertToMime);

        if (empty($extension)) {
            throw new InvalidMimeTypeException(
                mimeType: $convertToMime,
                message: InvalidMimeTypeException::ERROR_EXTENSION_GUESS_FAILED
            );
        }

        $convertedFilePath = $tmpFileSystem->extendPath($tmpFileSystem->getTmpFileName($extension));

        $this->vispImageManipulator->loadFile((string) $tmpFile->getRealPath());
        $this->vispImageManipulator->writeToFile($convertedFilePath);

        return AdapterFile::createFromBaseFile(
            file: AdapterFile::createFromBaseFile(
                new File($convertedFilePath),
                $tmpFileSystem
            ),
            filesystem: $tmpFileSystem
        );
    }
}
