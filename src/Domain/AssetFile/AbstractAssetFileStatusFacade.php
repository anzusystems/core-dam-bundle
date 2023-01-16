<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsProcessor;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory\ExternalProviderFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory\UrlFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\AssetFileStorageOperator;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\FileAttributesProcessor;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\MetadataProcessor;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Exception\DuplicateAssetFileException;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\DocumentMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\VideoMimeTypes;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractAssetFileStatusFacade implements AssetFileStatusInterface
{
    protected AssetFileStatusManager $assetStatusManager;
    protected AssetFileMessageDispatcher $assetFileMessageDispatcher;
    protected FileFactory $fileFactory;
    protected AssetFileStorageOperator $assetFileStorageOperator;
    protected AssetFileEventDispatcher $assetFileEventDispatcher;
    protected FileAttributesProcessor $fileAttributesPostProcessor;
    protected AssetFileRepository $assetFileRepository;
    protected IndexManager $indexManager;
    protected MetadataProcessor $metadataProcessor;
    protected EntityValidator $entityValidator;
    protected AssetTextsProcessor $displayTitleProcessor;
    protected ExternalProviderFileFactory $externalProviderFileFactory;
    protected UrlFileFactory $urlFileFactory;
    protected AssetManager $assetManager;
    protected DamLogger $damLogger;

    #[Required]
    public function setUrlFileFactory(UrlFileFactory $urlFileFactory): void
    {
        $this->urlFileFactory = $urlFileFactory;
    }

    #[Required]
    public function setDisplayTitleProcessor(AssetTextsProcessor $displayTitleProcessor): void
    {
        $this->displayTitleProcessor = $displayTitleProcessor;
    }

    #[Required]
    public function setEntityValidator(EntityValidator $entityValidator): void
    {
        $this->entityValidator = $entityValidator;
    }

    #[Required]
    public function setIndexManager(IndexManager $indexManager): void
    {
        $this->indexManager = $indexManager;
    }

    #[Required]
    public function setMetadataProcessor(MetadataProcessor $metadataProcessor): void
    {
        $this->metadataProcessor = $metadataProcessor;
    }

    #[Required]
    public function setAssetFileRepository(AssetFileRepository $assetFileRepository): void
    {
        $this->assetFileRepository = $assetFileRepository;
    }

    #[Required]
    public function setFileAttributesPostProcessor(FileAttributesProcessor $fileAttributesPostProcessor): void
    {
        $this->fileAttributesPostProcessor = $fileAttributesPostProcessor;
    }

    #[Required]
    public function setAssetFileEventDispatcher(AssetFileEventDispatcher $assetFileEventDispatcher): void
    {
        $this->assetFileEventDispatcher = $assetFileEventDispatcher;
    }

    #[Required]
    public function setAssetFileStatusManager(AssetFileStatusManager $assetFileStatusManager): void
    {
        $this->assetStatusManager = $assetFileStatusManager;
    }

    #[Required]
    public function setAssetFileMessageDispatcher(AssetFileMessageDispatcher $assetFileMessageDispatcher): void
    {
        $this->assetFileMessageDispatcher = $assetFileMessageDispatcher;
    }

    #[Required]
    public function setFileFactory(FileFactory $fileFactory): void
    {
        $this->fileFactory = $fileFactory;
    }

    #[Required]
    public function setAssetFileStorageOperator(AssetFileStorageOperator $assetFileStorageOperator): void
    {
        $this->assetFileStorageOperator = $assetFileStorageOperator;
    }

    #[Required]
    public function setAssetStatusManager(AssetFileStatusManager $assetStatusManager): void
    {
        $this->assetStatusManager = $assetStatusManager;
    }

    #[Required]
    public function setExternalProviderFileFactory(ExternalProviderFileFactory $externalProviderFileFactory): void
    {
        $this->externalProviderFileFactory = $externalProviderFileFactory;
    }

    #[Required]
    public function setAssetManager(AssetManager $assetManager): void
    {
        $this->assetManager = $assetManager;
    }

    #[Required]
    public function setDamLogger(DamLogger $damLogger): void
    {
        $this->damLogger = $damLogger;
    }

    /**
     * @throws ValidationException
     */
    public function finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile): AssetFile
    {
        $this->validateFullyUploaded($assetFile);
        $this->entityValidator->validateDto($assetFinishDto);
        $this->assetStatusManager->setNotifyTo($assetFile);
        $this->assetStatusManager->toUploaded($assetFile);

        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) to uploaded', (string) $assetFile->getId()));

        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);
        $this->assetFileMessageDispatcher->dispatchAssetFileChangeState($assetFile);

        return $assetFile;
    }

    /**
     * @throws FilesystemException
     * @throws NonUniqueResultException
     * @throws SerializerException
     * @throws TransportExceptionInterface
     */
    public function storeAndProcess(AssetFile $assetFile, ?AdapterFile $file = null): AssetFile
    {
        try {
            $file = $this->store($assetFile, $file);
            if ($assetFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Stored)) {
                $this->process($assetFile, $file);
            }
        } catch (DuplicateAssetFileException $duplicateAssetFileException) {
            $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) is duplicate', (string) $assetFile->getId()));
            $assetFile->getAssetAttributes()->setOriginAssetId(
                (string) $duplicateAssetFileException->getOldAsset()->getId()
            );
            $this->assetStatusManager->toDuplicate($assetFile);
            $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);
        } catch (AssetFileProcessFailed $assetFileProcessFailed) {
            $this->damLogger->error('AssetFileProcess', sprintf('Asset file (%s) failed', (string) $assetFile->getId()), $assetFileProcessFailed);

            $this->assetStatusManager->toFailed(
                $assetFile,
                $assetFileProcessFailed->getAssetFileFailedType()
            );
            $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);
        } catch (\Throwable $exception) {
            $this->damLogger->error('AssetFileProcess', sprintf('Asset file (%s) failed (not handled)', (string) $assetFile->getId()), $exception);
        }

        return $assetFile;
    }

    /**
     * @throws DuplicateAssetFileException
     * @throws FilesystemException
     * @throws NonUniqueResultException
     * @throws AssetFileProcessFailed
     * @throws TransportExceptionInterface
     */
    public function store(AssetFile $assetFile, ?AdapterFile $file = null): AdapterFile
    {
        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) to storing', (string) $assetFile->getId()));
        $this->assetStatusManager->toStoring($assetFile);
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);
        $file = $file ?: $this->createFile($assetFile);

        if (false === $this->supportsMimeType($assetFile, $file)) {
            throw new AssetFileProcessFailed(AssetFileFailedType::InvalidMimeType);
        }

        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) processing attributes', (string) $assetFile->getId()));
        $this->fileAttributesPostProcessor->process($assetFile, $file);
        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) checking duplicate', (string) $assetFile->getId()));
        $this->checkDuplicate($assetFile);
        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) saving to bucket', (string) $assetFile->getId()));
        $this->assetFileStorageOperator->save($assetFile, $file);

        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) to stored', (string) $assetFile->getId()));
        $this->assetStatusManager->toStored($assetFile);
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);

        return $file;
    }

    /**
     * @throws NonUniqueResultException
     * @throws SerializerException
     */
    public function process(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) to processing', (string) $assetFile->getId()));
        $this->assetStatusManager->toProcessing($assetFile);
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);

        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) processing asset file', (string) $assetFile->getId()));
        $this->processAssetFile($assetFile, $file);

        if (false === $assetFile->getFlags()->isProcessedMetadata()) {
            $this->metadataProcessor->process($assetFile, $file);
        }
        $this->damLogger->info('AssetFileProcess', sprintf('Asset file (%s) to processed', (string) $assetFile->getId()));
        $this->assetStatusManager->toProcessed($assetFile, false);
        $this->assetManager->updateExisting($assetFile->getAsset());
        $this->indexManager->index($assetFile->getAsset());

        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);

        return $assetFile;
    }

    protected function supportsMimeType(AssetFile $assetFile, AdapterFile $file): bool
    {
        $mimeType = (string) $file->getMimeType();

        return match ($assetFile->getAsset()->getAttributes()->getAssetType()) {
            AssetType::Image => in_array($mimeType, ImageMimeTypes::CHOICES, true),
            AssetType::Video => in_array($mimeType, VideoMimeTypes::CHOICES, true),
            AssetType::Audio => in_array($mimeType, AudioMimeTypes::CHOICES, true),
            AssetType::Document => in_array($mimeType, DocumentMimeTypes::CHOICES, true),
        };
    }

    /**
     * Concrete AssetFile processing (Image, Audio, ...)
     */
    abstract protected function processAssetFile(AssetFile $assetFile, AdapterFile $file): AssetFile;

    /**
     * @throws DuplicateAssetFileException
     * @throws NonUniqueResultException
     */
    abstract protected function checkDuplicate(AssetFile $assetFile): void;

    /**
     * @throws ForbiddenOperationException
     */
    private function validateFullyUploaded(AssetFile $assetFile): void
    {
        if (
            $assetFile->getAssetAttributes()->getUploadedSize() === $assetFile->getAssetAttributes()->getSize()
        ) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::ASSET_NOT_FULLY_UPLOADED);
    }

    /**
     * @throws FilesystemException
     * @throws TransportExceptionInterface
     */
    private function createFile(AssetFile $assetFile): AdapterFile
    {
        return match ($assetFile->getAssetAttributes()->getCreateStrategy()) {
            AssetFileCreateStrategy::Chunk => $this->fileFactory->createFromChunks($assetFile),
            AssetFileCreateStrategy::Download => $this->urlFileFactory->downloadFile((string) $assetFile->getAssetAttributes()->getOriginUrl()),
            AssetFileCreateStrategy::ExternalProvider => $this->externalProviderFileFactory->downloadFile(
                $assetFile->getAssetAttributes()->getOriginExternalProvider()
            ),
        };
    }
}
