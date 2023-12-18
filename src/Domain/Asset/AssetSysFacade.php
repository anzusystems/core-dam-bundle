<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManagerProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileMessageDispatcher;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysCreateDto;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use League\Flysystem\FilesystemException;

final class AssetSysFacade
{
    use IndexManagerAwareTrait;

    public function __construct(
        private readonly Validator $validator,
        private readonly AssetSysFactory $assetSysFactory,
        private readonly AssetFileManagerProvider $assetFileManagerProvider,
        private readonly AssetFileMessageDispatcher $assetFileMessageDispatcher,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function createFromDto(AssetFileSysCreateDto $dto): AssetFile
    {
        $assetFile = $this->assetSysFactory->createFromDto($dto);
        $this->facadeProvider->getStatusFacade($assetFile)->storeAndProcess($assetFile);

        $this->fileSystemProvider->getTmpFileSystem()->clearPaths();

        return $assetFile;
    }
}
