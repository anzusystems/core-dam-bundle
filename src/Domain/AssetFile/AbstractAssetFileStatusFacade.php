<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsProcessor;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory\ExternalProviderFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory\UrlFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\AssetFileStorageOperator;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\FileAttributesProcessor;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\MetadataProcessor;
use AnzuSystems\CoreDamBundle\Domain\Chunk\ChunkFileManager;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Exception\DuplicateAssetFileException;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetRefreshPropertiesMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

abstract class AbstractAssetFileStatusFacade implements AssetFileStatusInterface
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;
    use FileHelperTrait;
    use MessageBusAwareTrait;

    protected AssetFileStatusManager $assetStatusManager;
    protected AssetFileMessageDispatcher $assetFileMessageDispatcher;
    protected FileFactory $fileFactory;
    protected AssetFileStorageOperator $assetFileStorageOperator;
    protected AssetFileEventDispatcher $assetFileEventDispatcher;
    protected FileAttributesProcessor $fileAttributesPostProcessor;
    protected AssetFileRepository $assetFileRepository;
    protected MetadataProcessor $metadataProcessor;
    protected AssetTextsProcessor $displayTitleProcessor;
    protected ExternalProviderFileFactory $externalProviderFileFactory;
    protected UrlFileFactory $urlFileFactory;
    protected AssetManager $assetManager;
    protected DamLogger $damLogger;
    protected AssetFileCounter $assetFileCounter;
    protected ChunkFileManager $chunkFileManager;
    protected ResourceLocker $resourceLocker;

    #[Required]
    public function setAssetFileCounter(AssetFileCounter $assetFileCounter): void
    {
        $this->assetFileCounter = $assetFileCounter;
    }

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

    #[Required]
    public function setChunkFileManager(ChunkFileManager $chunkFileManager): void
    {
        $this->chunkFileManager = $chunkFileManager;
    }

    #[Required]
    public function setResourceLocker(ResourceLocker $resourceLocker): void
    {
        $this->resourceLocker = $resourceLocker;
    }

    /**
     * @throws SerializerException
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile): AssetFile
    {
        $this->validateFullyUploaded($assetFile);
        $this->validator->validate($assetFinishDto);
        $assetFile->getAssetAttributes()->setChecksum($assetFinishDto->getChecksum());
        $this->assetStatusManager->toUploaded($assetFile);

        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);
        $this->assetFileMessageDispatcher->dispatchAssetFileChangeState($assetFile);

        return $assetFile;
    }

    /**
     * @throws SerializerException
     */
    public function storeAndProcess(AssetFile $assetFile, ?AdapterFile $file = null): AssetFile
    {
        try {
            if ($assetFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Uploaded)) {
                $file = $this->store($assetFile, $file);
            }
            if (null === $file) {
                throw new RuntimeException(sprintf('AssetFile (%s) cant be processed without file', $assetFile->getId()));
            }
            if ($assetFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Stored)) {
                $this->chunkFileManager->clearChunks($assetFile);
                $this->process($assetFile, $file);
            }
        } catch (DuplicateAssetFileException $duplicateAssetFileException) {
            $assetFile->getAssetAttributes()->setOriginAssetId(
                (string) $duplicateAssetFileException->getOldAsset()->getId()
            );
            $this->assetFileEventDispatcher->dispatchDuplicatePreFlush(
                assetFile: $assetFile,
                originAssetFile: $duplicateAssetFileException->getOldAsset()
            );
            $this->assetStatusManager->toDuplicate($assetFile);
            $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);
        } catch (AssetFileProcessFailed $assetFileProcessFailed) {
            $this->assetStatusManager->toFailed(
                $assetFile,
                $assetFileProcessFailed->getAssetFileFailedType(),
                $assetFileProcessFailed
            );
            $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);
        } catch (Throwable $exception) {
            $this->assetStatusManager->toFailed(
                $assetFile,
                AssetFileFailedType::Unknown,
                $exception
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
     * @throws Throwable
     */
    public function store(AssetFile $assetFile, ?AdapterFile $file = null): AdapterFile
    {
        $file = $file ?: $this->createFile($assetFile);

        $this->fileAttributesPostProcessor->processAttributes($assetFile, $file);
        $this->fileAttributesPostProcessor->processChecksum($assetFile, $file);

        $lockName = $assetFile->getAssetType()->value . '_' . $assetFile->getLicence()->getId();
        $this->resourceLocker->lock($lockName);
        $originAssetFile = $this->checkDuplicate($assetFile);
        if ($originAssetFile) {
            $this->resourceLocker->unLock($lockName);
            throw new DuplicateAssetFileException($assetFile, $originAssetFile);
        }

        try {
            $this->assetManager->beginTransaction();
            $this->assetFileStorageOperator->save($assetFile, $file);
            $this->assetStatusManager->toStored($assetFile);
            $this->assetManager->commit();
            $this->resourceLocker->unLock($lockName);
        } catch (Throwable $exception) {
            $this->resourceLocker->unLock($lockName);
            $this->assetManager->rollback();

            throw $exception;
        }

        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);

        return $file;
    }

    /**
     * @throws NonUniqueResultException
     * @throws SerializerException
     * @throws Throwable
     */
    public function process(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        try {
            $this->assetManager->beginTransaction();

            $this->processAssetFile($assetFile, $file);
            if (false === $assetFile->getFlags()->isProcessedMetadata()) {
                $this->metadataProcessor->process($assetFile, $file);
            }
            $this->assetStatusManager->toProcessed($assetFile);
            $this->assetManager->commit();
            $this->messageBus->dispatch(new AssetRefreshPropertiesMessage((string) $assetFile->getAsset()->getId()));
        } catch (Throwable $exception) {
            $this->assetManager->rollback();

            throw $exception;
        }
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($assetFile);

        return $assetFile;
    }

    /**
     * Concrete AssetFile processing (Image, Audio, ...)
     */
    abstract protected function processAssetFile(AssetFile $assetFile, AdapterFile $file): AssetFile;

    /**
     * @throws DuplicateAssetFileException
     * @throws NonUniqueResultException
     */
    abstract protected function checkDuplicate(AssetFile $assetFile): ?AssetFile;

    /**
     * @throws ForbiddenOperationException
     * @throws InvalidArgumentException
     */
    private function validateFullyUploaded(AssetFile $assetFile): void
    {
        if (
            $this->assetFileCounter->getUploadedSize($assetFile) === $assetFile->getAssetAttributes()->getSize()
        ) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::ASSET_NOT_FULLY_UPLOADED);
    }

    /**
     * @throws AssetFileProcessFailed
     * @throws FilesystemException
     *
     * @psalm-suppress PossiblyNullArgument
     */
    private function createFile(AssetFile $assetFile): AdapterFile
    {
        return match ($assetFile->getAssetAttributes()->getCreateStrategy()) {
            AssetFileCreateStrategy::Chunk => $this->fileFactory->createFromChunks($assetFile),
            AssetFileCreateStrategy::Download => $this->urlFileFactory->downloadFile((string) $assetFile->getAssetAttributes()->getOriginUrl()),
            AssetFileCreateStrategy::ExternalProvider => $this->externalProviderFileFactory->downloadFile(
                $assetFile->getAssetAttributes()->getOriginExternalProvider()
            ),
            AssetFileCreateStrategy::Storage => $this->fileFactory->createFromStorage($assetFile),
        };
    }
}
