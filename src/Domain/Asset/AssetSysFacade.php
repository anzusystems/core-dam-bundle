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
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Exception\InvalidMimeTypeException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AbstractAssetFileSysDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysPathCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysUrlCreateDto;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Throwable;

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
        private readonly ImageDownloadFacade $imageDownloadFacade,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws ValidationException
     * @throws InvalidMimeTypeException
     * @throws NonUniqueResultException
     */
    public function createFromFileDto(AssetFileSysPathCreateDto $dto): AssetFile
    {
        $this->validator->validate($dto);
        $assetFile = $this->assetSysFactory->createFromDto($dto);
        $this->facadeProvider->getStatusFacade($assetFile)->storeAndProcess($assetFile);

        return $this->createFromDto($assetFile, $dto);
    }

    /**
     * @throws AssetFileProcessFailed
     * @throws FilesystemException
     * @throws NonUniqueResultException
     * @throws ValidationException
     */
    public function createFromUrlDto(AssetFileSysUrlCreateDto $dto): AssetFile
    {
        $this->validator->validate($dto);
        try {
            $this->assetMetadataManager->beginTransaction();
            $assetFile = $this->imageDownloadFacade->downloadSynchronous(
                assetLicence: $dto->getLicence(),
                url: $dto->getUrl()
            );
            $this->assetMetadataManager->commit();
        } catch (AssetFileProcessFailed $e) {
            $this->assetMetadataManager->rollback();

            throw $e;
        } catch (Throwable $e) {
            $this->assetMetadataManager->rollback();

            throw new RuntimeException('asset_file_create_from_url_failed', 0, $e);
        }

        return $this->createFromDto($assetFile, $dto);
    }

    /**
     * @throws FilesystemException
     * @throws NonUniqueResultException
     */
    public function createFromDto(AssetFile $assetFile, AbstractAssetFileSysDto $dto): AssetFile
    {
        $this->assetMetadataManager->updateFromCustomData(
            $assetFile->getAsset(),
            [
                ...$assetFile->getAsset()->getMetadata()->getCustomData(),
                ...$dto->getCustomData(),
            ]
        );
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
