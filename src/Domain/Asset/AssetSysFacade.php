<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataManager;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\Keyword\KeywordProvider;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDistributionStarter;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDownloadFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Exception\InvalidMimeTypeException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetRefreshPropertiesMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AbstractAssetFileSysDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AbstractAssetFileSysUrlCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysPathCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysUrlCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\VideoFileSysUrlCreateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use Closure;
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
        private readonly VideoDownloadFacade $videoDownloadFacade,
        private readonly ImageFileRepository $imageFileRepository,
        private readonly VideoDistributionStarter $videoDistributionStarter,
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

        $this->createFromDto($assetFile, $dto);
        $this->generatePublicRoute($assetFile, $dto->isGeneratePublicRoute());

        return $assetFile;
    }

    /**
     * @throws AssetFileProcessFailed
     * @throws ValidationException
     */
    public function createFromUrlDto(AssetFileSysUrlCreateDto $dto): AssetFile
    {
        $this->validator->validate($dto);

        return $this->createFromUrl(
            fn (): AssetFile => $this->imageDownloadFacade->downloadSynchronous(
                assetLicence: $dto->getLicence(),
                url: $dto->getUrl(),
                setupData: function (AssetFile $assetFile) use ($dto): void {
                    $this->createFromDto($assetFile, $dto);
                    $this->generatePublicRoute($assetFile, $dto->isGeneratePublicRoute());
                    $this->setupKeywords($assetFile, $dto);
                    $this->setupAuthors($assetFile, $dto);
                }
            )
        );
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

        return $assetFile;
    }

    /**
     * @throws ValidationException
     * @throws AssetFileProcessFailed
     * @throws NonUniqueResultException
     */
    public function createVideoFromUrlDto(VideoFileSysUrlCreateDto $dto): VideoFile
    {
        $this->validator->validate($dto);
        $imagePreview = null === $dto->getImagePreviewAssetFileId()
            ? null
            : $this->imageFileRepository->findProcessedByIdAndLicence($dto->getImagePreviewAssetFileId(), $dto->getLicence());

        $videoFile = $this->createFromUrl(
            fn (): VideoFile => $this->videoDownloadFacade->createForDownload(
                assetLicence: $dto->getLicence(),
                url: $dto->getUrl(),
                imagePreview: $imagePreview,
                setupData: function (AssetFile $assetFile) use ($dto): void {
                    $this->createFromDto($assetFile, $dto);
                    $this->setupKeywords($assetFile, $dto);
                    $this->setupAuthors($assetFile, $dto);
                }
            )
        );

        $status = $videoFile->getAssetAttributes()->getStatus();
        if ($status->is(AssetFileProcessStatus::Uploaded)) {
            $this->messageBus->dispatch(new VideoFileChangeStateMessage($videoFile, $dto->getDistributionServices()));

            return $videoFile;
        }
        if ($status->is(AssetFileProcessStatus::Processed) && [] !== $dto->getDistributionServices()) {
            $this->videoDistributionStarter->start($videoFile, $dto->getDistributionServices());
        }

        return $videoFile;
    }

    public function setupKeywords(AssetFile $assetFile, AbstractAssetFileSysUrlCreateDto $dto): void
    {
        foreach ($dto->getKeywords() as $keywordName) {
            $keyword = $this->keywordProvider->provideKeyword($keywordName, $assetFile->getExtSystem());
            if ($keyword instanceof Keyword) {
                $assetFile->getAsset()->addKeyword($keyword);
            }
        }
    }

    public function setupAuthors(AssetFile $assetFile, AbstractAssetFileSysUrlCreateDto $dto): void
    {
        foreach ($dto->getAuthors() as $authorName) {
            $author = $this->authorProvider->provideByTitle($authorName, $assetFile->getExtSystem());
            if ($author instanceof Author) {
                $assetFile->getAsset()->addAuthor($author);
            }
        }
    }

    /**
     * @template T of AssetFile
     *
     * @param Closure(): T $create
     *
     * @return T
     *
     * @throws AssetFileProcessFailed
     */
    private function createFromUrl(Closure $create): AssetFile
    {
        try {
            $this->assetMetadataManager->beginTransaction();
            $assetFile = $create();

            $indexEntities = array_values([
                ...$assetFile->getAsset()->getAuthors(),
                ...$assetFile->getAsset()->getKeywords(),
            ]);
            if (false === empty($indexEntities)) {
                $this->indexManager->indexBulk($indexEntities);
            }

            $this->assetMetadataManager->commit();
        } catch (AssetFileProcessFailed $e) {
            $this->assetMetadataManager->rollback();

            throw $e;
        } catch (Throwable $e) {
            $this->assetMetadataManager->rollback();

            throw new RuntimeException('asset_file_create_from_url_failed', 0, $e);
        }

        $this->messageBus->dispatch(new AssetRefreshPropertiesMessage((string) $assetFile->getAsset()->getId()));

        return $assetFile;
    }

    private function generatePublicRoute(AssetFile $assetFile, bool $generatePublicRoute): void
    {
        if ($generatePublicRoute && empty($assetFile->getAssetAttributes()->getOriginAssetId())) {
            $this->assetFileRouteFacade->makePublicAssetFile($assetFile);
        }
    }
}
