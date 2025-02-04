<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use League\Flysystem\FilesystemException;

final class AssetFileStorageOperator
{
    use FileHelperTrait;
    private const string ORIG_SUFFIX = 'orig';

    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly NameGenerator $nameGenerator,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function save(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $path = $this->nameGenerator->generatePath(
            extension: $this->fileHelper->guessExtension($assetFile->getAssetAttributes()->getMimeType()),
            dateDirPath: true,
            fileNameSuffix: $assetFile instanceof ImageFile ? self::ORIG_SUFFIX : App::EMPTY_STRING
        );
        $fileSystem = $this->fileSystemProvider->getFilesystemByStorable($assetFile);

        $fileSystem->writeStream(
            $path->getRelativePath(),
            $file->readStream(),
        );

        $assetFile->getAssetAttributes()
            ->setFilePath($path->getRelativePath());

        return $assetFile;
    }

    /**
     * @throws FilesystemException
     */
    public function copyToAssetFile(AssetFile $sourceAssetFile, AssetFile $targetAssetFile): void
    {
        $path = $this->nameGenerator->generatePath(
            $this->fileHelper->guessExtension($sourceAssetFile->getAssetAttributes()->getMimeType()),
            true
        );

        $sourceFileSystem = $this->fileSystemProvider->getFilesystemByStorable($sourceAssetFile);
        $targetFileSystem = $this->fileSystemProvider->getFilesystemByStorable($targetAssetFile);

        $targetFileSystem->writeStream(
            $path->getRelativePath(),
            $sourceFileSystem->readStream($sourceAssetFile->getFilePath())
        );

        $targetAssetFile->getAssetAttributes()
            ->setFilePath($path->getRelativePath());
    }
}
