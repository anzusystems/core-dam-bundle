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
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
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

    /**
     * @throws ValidationException
     */
    public function finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile): AssetFile
    {
        $this->validateFullyUploaded($assetFile);
        $this->entityValidator->validateDto($assetFinishDto);
        $this->assetStatusManager->setNotifyTo($assetFile);
        $this->assetStatusManager->toUploaded($assetFile);
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
    public function storeAndProcess(AssetFile $assetFile, ?File $file = null): AssetFile
    {
        try {
            $file = $this->store($assetFile, $file);
            if ($assetFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Stored)) {
                $this->process($assetFile, $file);
            }
        } catch (DuplicateAssetFileException $duplicateAssetFileException) {
            $assetFile->getAssetAttributes()->setOriginAssetId(
                (string) $duplicateAssetFileException->getOldAsset()->getId()
            );
            $this->assetStatusManager->toDuplicate($assetFile);
            $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);
        } catch (AssetFileProcessFailed $assetFileProcessFailed) {
            $this->assetStatusManager->toFailed(
                $assetFileProcessFailed->getAssetFile(),
                $assetFileProcessFailed->getAssetFileFailedType()
            );
            $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);
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
    public function store(AssetFile $assetFile, ?File $file = null): File
    {
        $this->assetStatusManager->toStoring($assetFile);
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);
        $file = $file ?: $this->createFile($assetFile);

        if (false === $this->supportsMimeType($assetFile, $file)) {
            throw new AssetFileProcessFailed($assetFile, AssetFileFailedType::InvalidMimeType);
        }

        $this->fileAttributesPostProcessor->process($assetFile, $file);
        $this->checkDuplicate($assetFile);
        $this->assetFileStorageOperator->save($assetFile, $file);

        $this->assetStatusManager->toStored($assetFile);
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);

        return $file;
    }

    /**
     * @throws NonUniqueResultException
     * @throws SerializerException
     */
    public function process(AssetFile $assetFile, File $file): AssetFile
    {
        $this->assetStatusManager->toProcessing($assetFile);
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);

        $this->processAssetFile($assetFile, $file);

        $assetFile->getAsset()->getAttributes()->setStatus(AssetStatus::WithFile);
        if (false === $assetFile->getFlags()->isProcessedMetadata()) {
            $this->metadataProcessor->process($assetFile, $file);
        }
        $this->assetStatusManager->toProcessed($assetFile, false);
        $this->assetManager->updateExisting($assetFile->getAsset(), false);
        $this->indexManager->index($assetFile->getAsset());

        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);

        return $assetFile;
    }

    protected function supportsMimeType(AssetFile $assetFile, File $file): bool
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
    abstract protected function processAssetFile(AssetFile $assetFile, File $file): AssetFile;

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
    private function createFile(AssetFile $assetFile): File
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
