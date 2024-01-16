<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManagerProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileMessageDispatcher;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\InvalidMimeTypeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysCreateDto;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Doctrine\ORM\NonUniqueResultException;
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
        private readonly AssetFileRouteFacade $assetFileRouteFacade,
        private readonly AssetMetadataManager $assetMetadataManager,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws ValidationException
     * @throws InvalidMimeTypeException
     * @throws NonUniqueResultException
     */
    public function createFromDto(AssetFileSysCreateDto $dto): AssetFile
    {
        $this->validator->validate($dto);
        $assetFile = $this->assetSysFactory->createFromDto($dto);
        $this->assetMetadataManager->updateFromCustomData($assetFile->getAsset(), $dto->getCustomData());
        $this->facadeProvider->getStatusFacade($assetFile)->storeAndProcess($assetFile);
        $this->fileSystemProvider->getTmpFileSystem()->clearPaths();

        if (
            $dto->isGeneratePublicRoute() &&
            empty($assetFile->getAssetAttributes()->getOriginAssetId())
        ) {
            $this->assetFileRouteFacade->makePublicAssetFile($assetFile);
        }

        return $assetFile;
    }
}
