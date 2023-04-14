<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use League\Flysystem\FilesystemException;

final readonly class AssetFileStorageOperator
{
    public function __construct(
        private FileSystemProvider $fileSystemProvider,
        private NameGenerator $nameGenerator,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function save(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $path = $this->nameGenerator->generatePath(FileHelper::guessExtension((string) $file->getMimeType()), true);
        $fileSystem = $this->fileSystemProvider->getFilesystemByStorable($assetFile);

        $fileSystem->writeStream(
            $path->getRelativePath(),
            $file->readStream(),
        );

        $assetFile->getAssetAttributes()
            ->setFilePath($path->getRelativePath())
            ->setMimeType((string) $file->getMimeType());

        return $assetFile;
    }
}
