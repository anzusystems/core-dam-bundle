<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory;

use AnzuSystems\CoreDamBundle\AssetExternalProvider\AssetExternalProviderContainer;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginExternalProvider;
use League\Flysystem\FilesystemException;

final class ExternalProviderFileFactory
{
    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetExternalProviderContainer $assetExternalProviderContainer,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function downloadFile(OriginExternalProvider $externalProvider): AdapterFile
    {
        $stream = $this->assetExternalProviderContainer
            ->get($externalProvider->getProviderName())
            ->download($externalProvider->getId());

        $fileSystem = $this->fileSystemProvider->getTmpFileSystem();

        $baseFile = $fileSystem->writeTmpFileFromStream($stream);

        return AdapterFile::createFromBaseFile($baseFile, $fileSystem);
    }
}
