<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;
use League\Flysystem\FilesystemException;

final class AssetFileStorageOperator
{
    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly NameGenerator $nameGenerator,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function save(AssetFile $assetFile, File $file): AssetFile
    {
        $path = $this->nameGenerator->generatePath(FileHelper::guessExtension($file->getMimeType()));
        $fileSystem = $this->fileSystemProvider->getFilesystemByStorable($assetFile);

        $fileSystem->writeStream(
            $path->getRelativePath(),
            $file->readStream(),
        );

        $assetFile->getAssetAttributes()
            ->setFilePath($path->getRelativePath())
            ->setMimeType($file->getMimeType());

        return $assetFile;
    }
}
