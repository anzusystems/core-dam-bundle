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
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\Keyword\KeywordProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Exception\InvalidMimeTypeException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetRefreshPropertiesMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AbstractAssetFileSysDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysPathCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysUrlCreateDto;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Throwable;

final class AssetSysFacade
{
    use IndexManagerAwareTrait;
    use MessageBusAwareTrait;

    public function __construct(
        private readonly Validator $validator,
        private readonly AssetSysFactory $assetSysFactory,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetFileRouteFacade $assetFileRouteFacade,
        private readonly AssetMetadataManager $assetMetadataManager,
        private readonly ImageDownloadFacade $imageDownloadFacade,
        private readonly KeywordProvider $keywordProvider,
        private readonly AuthorProvider $authorProvider,
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
     * @throws ValidationException
     */
    public function createFromUrlDto(AssetFileSysUrlCreateDto $dto): AssetFile
    {
        $this->validator->validate($dto);

        try {
            $this->assetMetadataManager->beginTransaction();
            $assetFile = $this->imageDownloadFacade->downloadSynchronous(
                assetLicence: $dto->getLicence(),
                url: $dto->getUrl(),
                setupData: function (AssetFile $assetFile) use ($dto): void {
                    $this->createFromDto($assetFile, $dto);
                    $this->setupKeywords($assetFile, $dto);
                    $this->setupAuthors($assetFile, $dto);
                }
            );

            $indexEntities = array_values([
                ...$assetFile->getAsset()->getAuthors(),
                ...$assetFile->getAsset()->getKeywords(),
            ]);
            if (false === empty($indexEntities)) {
                $this->indexManager->indexBulk($indexEntities);
            }

            $this->assetMetadataManager->commit();
            $this->messageBus->dispatch(new AssetRefreshPropertiesMessage((string) $assetFile->getAsset()->getId()));

            return $assetFile;
        } catch (AssetFileProcessFailed $e) {
            $this->assetMetadataManager->rollback();

            throw $e;
        } catch (Throwable $e) {
            $this->assetMetadataManager->rollback();

            throw new RuntimeException('asset_file_create_from_url_failed', 0, $e);
        }
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

    public function setupKeywords(AssetFile $assetFile, AssetFileSysUrlCreateDto $dto): void
    {
        foreach ($dto->getKeywords() as $keywordName) {
            $keyword = $this->keywordProvider->provideKeyword($keywordName, $assetFile->getExtSystem());
            if ($keyword instanceof Keyword) {
                $assetFile->getAsset()->addKeyword($keyword);
            }
        }
    }

    public function setupAuthors(AssetFile $assetFile, AssetFileSysUrlCreateDto $dto): void
    {
        foreach ($dto->getAuthors() as $authorName) {
            $author = $this->authorProvider->provideByTitle($authorName, $assetFile->getExtSystem());
            if ($author instanceof Author) {
                $assetFile->getAsset()->addAuthor($author);
            }
        }
    }
}
