<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\AssetExternalProvider\AssetExternalProviderContainer;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\AssetHasFile\AssetHasFileFactory;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileDeleteEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\AssetFileVersionUsedException;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\UploadAssetFromExternalProviderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDtoInterface;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Repository\AbstractAssetFileRepository;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

/**
 * @template-covariant T of AssetFile
 */
abstract class AssetFileFacade
{
    protected AssetManager $assetManager;
    protected AssetFactory $assetFactory;
    protected EntityValidator $entityValidator;
    protected AssetFileStatusManager $assetStatusManager;
    protected MessageBusInterface $messageBus;
    protected ExtSystemConfigurationProvider $extSystemConfigurationProvider;
    protected AssetHasFileFactory $assetHasFileFactory;
    protected IndexManager $indexManager;
    protected FileStash $fileDeleteStash;
    protected AssetFileDeleteEventDispatcher $assetFileDeleteEventDispatcher;
    protected AssetExternalProviderContainer $assetExternalProviderContainer;

    #[Required]
    public function setAssetFileDeleteEventDispatcher(AssetFileDeleteEventDispatcher $assetFileDeleteEventDispatcher): void
    {
        $this->assetFileDeleteEventDispatcher = $assetFileDeleteEventDispatcher;
    }

    #[Required]
    public function setAssetFileDeleteManager(FileStash $fileDeleteStash): void
    {
        $this->fileDeleteStash = $fileDeleteStash;
    }

    #[Required]
    public function setIndexManager(IndexManager $indexManager): void
    {
        $this->indexManager = $indexManager;
    }

    #[Required]
    public function setAssetHasFileFactory(AssetHasFileFactory $assetHasFileFactory): void
    {
        $this->assetHasFileFactory = $assetHasFileFactory;
    }

    #[Required]
    public function setExtSystemConfigurationProvider(ExtSystemConfigurationProvider $extSystemConfigurationProvider): void
    {
        $this->extSystemConfigurationProvider = $extSystemConfigurationProvider;
    }

    #[Required]
    public function setEntityValidator(EntityValidator $entityValidator): void
    {
        $this->entityValidator = $entityValidator;
    }

    #[Required]
    public function setAssetStatusManager(AssetFileStatusManager $assetStatusManager): void
    {
        $this->assetStatusManager = $assetStatusManager;
    }

    #[Required]
    public function setAssetManager(AssetManager $assetManager): void
    {
        $this->assetManager = $assetManager;
    }

    #[Required]
    public function setAssetFactory(AssetFactory $assetFactory): void
    {
        $this->assetFactory = $assetFactory;
    }

    #[Required]
    public function setMessageBus(MessageBusInterface $messageBus): void
    {
        $this->messageBus = $messageBus;
    }

    #[Required]
    public function setAssetExternalProviderContainer(AssetExternalProviderContainer $providerContainer): void
    {
        $this->assetExternalProviderContainer = $providerContainer;
    }

    /**
     * @return T
     *
     * @throws ValidationException
     */
    public function createAssetFilesFromExternalProvider(
        UploadAssetFromExternalProviderDto $uploadDto,
        AssetLicence $assetLicence,
    ): AssetFile {
        $uploadDto->setAssetLicence($assetLicence);
        $this->entityValidator->validateDto($uploadDto);
        $imageDto = $this->assetExternalProviderContainer
            ->get($uploadDto->getExternalProvider())
            ->getById($uploadDto->getId())
        ;

        try {
            $this->getManager()->beginTransaction();
            $assetFile = $this->getFactory()->createFromExternalProvider(
                providerName: $uploadDto->getExternalProvider(),
                assetDto: $imageDto,
                assetLicence: $assetLicence,
            );
            $this->assetStatusManager->setNotifyTo($assetFile);
            $this->getManager()->flush();
            $this->indexManager->index($assetFile->getAsset()->getAsset());
            $this->getManager()->commit();
        } catch (Throwable $exception) {
            $this->getManager()->rollback();

            throw new RuntimeException('asset_file_upload_failed', 0, $exception);
        }

        $this->messageBus->dispatch(new AssetFileChangeStateMessage($assetFile));

        return $assetFile;
    }

    /**
     * @return T
     *
     * @throws ValidationException
     */
    public function createAssetFile(AssetFileAdmCreateDto $createDto, AssetLicence $assetLicence): AssetFile
    {
        $this->entityValidator->validateDto($createDto);
        $this->validateAssetSize($createDto, $assetLicence);
        $assetFile = $this->getFactory()->createFromAdmDto($assetLicence, $createDto);

        $this->assetFactory->createForAssetFile($assetFile, $assetLicence);

        try {
            $this->getManager()->beginTransaction();
            $this->getManager()->create($assetFile);
            $this->indexManager->index($assetFile->getAsset()->getAsset());
            $this->getManager()->commit();

            return $assetFile;
        } catch (Throwable $exception) {
            $this->getManager()->rollback();

            throw new RuntimeException('asset_file_create_failed', 0, $exception);
        }
    }

    /**
     * @return T
     *
     * @throws AssetFileVersionUsedException
     * @throws ValidationException
     */
    public function addAssetFileToAsset(Asset $asset, AssetFileAdmCreateDto $createDto, string $version): AssetFile
    {
        $this->validateAssetType($asset, $createDto);
        $this->validateFileVersion($asset, $version);
        $this->entityValidator->validateDto($createDto);

        $assetFile = $this->getRepository()->getByAssetAndFileVersionName($asset->getId(), $version);

        if ($assetFile instanceof AssetFile) {
            throw new AssetFileVersionUsedException($assetFile, $version);
        }

        $assetFile = $this->getFactory()->createFromAdmDto($asset->getLicence(), $createDto);

        try {
            $this->getManager()->beginTransaction();
            $this->getManager()->create($assetFile);
            $this->assetHasFileFactory->createRelation($asset, $assetFile, $version);

            $this->indexManager->index($assetFile->getAsset()->getAsset());
            $this->getManager()->commit();

            return $assetFile;
        } catch (Throwable $exception) {
            $this->getManager()->rollback();

            throw new RuntimeException('image_create_failed', 0, $exception);
        }
    }

    public function delete(AssetFile $assetFile): void
    {
        try {
            $this->getManager()->beginTransaction();
            $deleteId = $assetFile->getId();
            $asset = $assetFile->getAsset()->getAsset();
            $this->getManager()->delete($assetFile);

            if ($asset->getFiles()->isEmpty()) {
                $assetFile->getAsset()->getAsset()->getAttributes()->setStatus(AssetStatus::Draft);
            }

            $this->indexManager->index($asset);
            $this->fileDeleteStash->emptyAll();
            $this->getManager()->commit();

            $this->assetFileDeleteEventDispatcher->dispatchFileDelete(
                $deleteId,
                (string) $asset->getId(),
                $assetFile,
                $asset->getAttributes()->getAssetType(),
                $assetFile->getModifiedBy()
            );
        } catch (Throwable $exception) {
            $this->getManager()->rollback();

            throw new RuntimeException('asset_file_delete_failed', 0, $exception);
        }
    }

    /**
     * @throws ForbiddenOperationException
     */
    protected function validateAssetType(Asset $asset, AssetFileAdmCreateDtoInterface $createDto): void
    {
        if ($asset->getAttributes()->getAssetType()->is($createDto->getAssetType())) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_ASSET_TYPE);
    }

    /**
     * @throws ForbiddenOperationException
     */
    protected function validateAssetSize(AssetFileAdmCreateDto $createDto, AssetLicence $assetLicence): void
    {
        $configuration = $this->extSystemConfigurationProvider->getAssetConfiguration(
            $assetLicence->getExtSystem()->getSlug(),
            $createDto->getAssetType()
        );

        if ($createDto->getSize() <= $configuration->getSizeLimit()) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::ASSET_SIZE_TOO_LARGE);
    }

    /**
     * @throws ForbiddenOperationException
     */
    protected function validateFileVersion(Asset $asset, string $version): void
    {
        $assetTypeConfiguration = $this->extSystemConfigurationProvider->getAssetConfiguration(
            $asset->getLicence()->getExtSystem()->getSlug(),
            $asset->getAttributes()->getAssetType()
        );

        if (in_array($version, $assetTypeConfiguration->getFileVersions()->getVersions(), true)) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_FILE_VERSION);
    }

    abstract protected function getManager(): AssetFileManager;

    abstract protected function getFactory(): AssetFileFactory;

    abstract protected function getRepository(): AbstractAssetFileRepository;
}
