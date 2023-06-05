<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\AssetExternalProvider\AssetExternalProviderContainer;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileDeleteEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\AssetSlotUsedException;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\UploadAssetFromExternalProviderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDtoInterface;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Repository\AbstractAssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetSlotRepository;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

/**
 * @template T of AssetFile
 */
abstract class AbstractAssetFileFacade
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;

    private const DUPLICATE_FILES_DELETE_MODIFIER = '-1 week';
    private const DUPLICATE_FILES_DELETE_LIMIT = 100;

    protected AssetManager $assetManager;
    protected AssetFactory $assetFactory;
    protected AssetFileStatusManager $assetStatusManager;
    protected MessageBusInterface $messageBus;
    protected ExtSystemConfigurationProvider $extSystemConfigurationProvider;
    protected AssetSlotFactory $assetSlotFactory;
    protected FileStash $fileDeleteStash;
    protected AssetFileDeleteEventDispatcher $assetFileDeleteEventDispatcher;
    protected AssetExternalProviderContainer $assetExternalProviderContainer;
    protected AssetSlotRepository $assetSlotRepository;
    protected AssetFileRepository $assetFileRepository;
    protected ConfigurationProvider $configurationProvider;

    #[Required]
    public function setAssetSlotRepository(AssetSlotRepository $assetSlotRepository): void
    {
        $this->assetSlotRepository = $assetSlotRepository;
    }

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
    public function setAssetSlotFactory(AssetSlotFactory $assetSlotFactory): void
    {
        $this->assetSlotFactory = $assetSlotFactory;
    }

    #[Required]
    public function setExtSystemConfigurationProvider(ExtSystemConfigurationProvider $extSystemConfigurationProvider): void
    {
        $this->extSystemConfigurationProvider = $extSystemConfigurationProvider;
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

    #[Required]
    public function setAssetFileRepository(AssetFileRepository $assetFileRepository): void
    {
        $this->assetFileRepository = $assetFileRepository;
    }

    #[Required]
    public function setConfigurationProvider(ConfigurationProvider $configurationProvider): void
    {
        $this->configurationProvider = $configurationProvider;
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
        $this->validator->validate($uploadDto);
        $this->validateLimitedAssetLicenceFileCount($assetLicence);
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
            $this->getManager()->flush();
            $this->indexManager->index($assetFile->getAsset());
            $this->getManager()->commit();
        } catch (Throwable $exception) {
            $this->getManager()->rollback();

            throw new RuntimeException('asset_file_upload_failed', 0, $exception);
        }

        $this->messageBus->dispatch(new VideoFileChangeStateMessage($assetFile));

        return $assetFile;
    }

    public function deleteFailedAndDuplicates(): int
    {
        $dateTime = App::getAppDate()->modify(self::DUPLICATE_FILES_DELETE_MODIFIER);
        $assetFiles = $this->getRepository()->findToDelete($dateTime, self::DUPLICATE_FILES_DELETE_LIMIT);

        foreach ($assetFiles as $files) {
            /** @psalm-suppress InvalidArgument */
            $this->delete($files);
        }

        return $assetFiles->count();
    }

    /**
     * @return T
     *
     * @throws ValidationException
     */
    public function createAssetFile(AssetFileAdmCreateDto $createDto, AssetLicence $assetLicence): AssetFile
    {
        $this->validator->validate($createDto);
        $this->validateAssetSize($createDto, $assetLicence);
        $this->validateLimitedAssetLicenceFileCount($assetLicence);
        $assetFile = $this->getFactory()->createFromAdmDto($assetLicence, $createDto);

        $this->assetFactory->createForAssetFile($assetFile, $assetLicence);

        try {
            $this->getManager()->beginTransaction();
            $this->getManager()->create($assetFile);
            $this->indexManager->index($assetFile->getAsset());
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
     * @throws AssetSlotUsedException
     * @throws ValidationException
     */
    public function addAssetFileToAsset(Asset $asset, AssetFileAdmCreateDto $createDto, string $slotName): AssetFile
    {
        $this->validateAssetType($asset, $createDto);
        $this->validateSlotTitle($asset, $slotName);
        $this->validator->validate($createDto);
        $this->validateLimitedAssetLicenceFileCount($asset->getLicence());

        $slot = $this->assetSlotRepository->findSlotByAssetAndTitle((string) $asset->getId(), $slotName);

        if ($slot) {
            throw new AssetSlotUsedException($slot->getAssetFile(), $slotName);
        }

        $assetFile = $this->getFactory()->createFromAdmDto($asset->getLicence(), $createDto);

        try {
            $this->getManager()->beginTransaction();
            $this->getManager()->create($assetFile);
            $this->assetSlotFactory->createRelation($asset, $assetFile, $slotName);

            $this->indexManager->index($assetFile->getAsset());
            $this->getManager()->commit();

            return $assetFile;
        } catch (Throwable $exception) {
            $this->getManager()->rollback();

            throw new RuntimeException('image_create_failed', 0, $exception);
        }
    }

    /**
     * @psalm-param T $assetFile
     */
    public function delete(AssetFile $assetFile): void
    {
        try {
            $this->getManager()->beginTransaction();
            $deleteId = $assetFile->getId();
            $asset = $assetFile->getAsset();

            if ($assetFile === $asset->getMainFile()) {
                $asset->setMainFile(null);
            }

            $this->getManager()->delete($assetFile);

            if ($asset->getSlots()->isEmpty()) {
                $assetFile->getAsset()->getAttributes()->setStatus(AssetStatus::Draft);
            }

            $this->assetManager->updateExisting($asset);
            $this->indexManager->index($asset);
            $this->fileDeleteStash->emptyAll();
            $this->getManager()->commit();

            $this->assetFileDeleteEventDispatcher->dispatchFileDelete(
                (string) $deleteId,
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
    protected function validateSlotTitle(Asset $asset, string $slotName): void
    {
        $assetTypeConfiguration = $this->extSystemConfigurationProvider->getAssetConfiguration(
            $asset->getLicence()->getExtSystem()->getSlug(),
            $asset->getAttributes()->getAssetType()
        );

        if (in_array($slotName, $assetTypeConfiguration->getSlots()->getSlots(), true)) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_ASSET_SLOT);
    }

    /**
     * @throws ForbiddenOperationException
     */
    protected function validateLimitedAssetLicenceFileCount(AssetLicence $licence): void
    {
        $settings = $this->configurationProvider->getSettings();
        if ($licence->isNotLimitedFiles()) {
            return;
        }
        $maxFilesCount = $settings->getLimitedAssetLicenceFilesCount();
        $uploadedCount = $this->assetFileRepository->getLimitedCountByAssetLicence(
            licence: $licence,
            maxFilesCount: $maxFilesCount,
        );
        if ($uploadedCount < $maxFilesCount) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::FILE_UPLOAD_TOO_MANY_FILES);
    }

    /**
     * @return AssetFileManager<T>
     */
    abstract protected function getManager(): AssetFileManager;

    /**
     * @return AssetFileFactory<T>
     */
    abstract protected function getFactory(): AssetFileFactory;

    abstract protected function getRepository(): AbstractAssetFileRepository;
}
