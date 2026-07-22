<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManagerProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetEventDispatcher;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileDeleteEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\DependencyExistsException;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Traits\FileStashAwareTrait;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Doctrine\Common\Collections\ReadableCollection;
use League\Flysystem\FilesystemException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

class AssetFacade
{
    use FileStashAwareTrait;
    use IndexManagerAwareTrait;
    use ValidatorAwareTrait;

    private const string UNFINISHED_UPLOADS_DELETE_MODIFIER = '-1 week';
    private const int UNFINISHED_UPLOADS_DELETE_LIMIT = 100;

    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly AssetFactory $assetFactory,
        private readonly MessageBusInterface $messageBus,
        private readonly AssetStatusManager $assetStatusManager,
        private readonly AssetFileManagerProvider $assetFileManagerProvider,
        private readonly AssetEventDispatcher $assetEventDispatcher,
        private readonly AssetFileDeleteEventDispatcher $assetFileDeleteEventDispatcher,
        private readonly AssetRepository $assetRepository,
        private readonly ExtSystemRepository $extSystemRepository,
        private readonly AudioFileRepository $audioFileRepository,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function update(Asset $asset, AssetAdmUpdateDto $newAssetDto): Asset
    {
        $newAssetDto->setAsset($asset);
        $this->validator->validate($newAssetDto, $asset);

        try {
            $this->assetManager->beginTransaction();
            $this->assetManager->update($asset, $newAssetDto);
            $this->assetManager->updateExisting($asset);
            $this->indexManager->index($asset);
            $this->assetManager->commit();
        } catch (Throwable $exception) {
            $this->assetManager->rollback();

            throw new RuntimeException('asset_update_failed', 0, $exception);
        }

        return $asset;
    }

    /**
     * @throws ValidationException
     */
    public function create(AssetAdmCreateDto $assetAdmCreateDto, AssetLicence $assetLicence): Asset
    {
        $this->validator->validate($assetAdmCreateDto);
        $asset = $this->assetFactory->createFromAdmDto($assetAdmCreateDto, $assetLicence);
        $asset->getAssetFlags()->setAutoDeleteUnprocessed(false);

        try {
            $this->assetManager->beginTransaction();
            $this->assetManager->create($asset);
            $this->assetManager->commit();

            return $asset;
        } catch (Throwable $exception) {
            $this->assetManager->rollback();

            throw new RuntimeException('asset_create_failed', 0, $exception);
        }
    }

    public function deleteUnfinishedUploads(): int
    {
        $dateTime = App::getAppDate()->modify(self::UNFINISHED_UPLOADS_DELETE_MODIFIER);
        $assets = $this->assetRepository->findToDelete($dateTime, self::UNFINISHED_UPLOADS_DELETE_LIMIT);

        foreach ($assets as $asset) {
            $this->toDeleting($asset);
        }

        return $assets->count();
    }

    public function toDeleting(Asset $asset): void
    {
        foreach ($asset->getSlots() as $slot) {
            if (false === $this->assetFileManagerProvider->getManager($slot->getAssetFile())->canBeRemoved($slot->getAssetFile())) {
                throw new ForbiddenOperationException(ForbiddenOperationException::FILE_IS_USED);
            }
        }
        $this->assetManager->beginTransaction();

        try {
            $this->assetStatusManager->toDeleting($asset);
            $this->assetManager->commit();

            $this->messageBus->dispatch(new AssetChangeStateMessage($asset));
        } catch (Throwable $exception) {
            if ($this->assetManager->isTransactionActive()) {
                $this->assetManager->rollback();
            }

            throw new RuntimeException('asset_deleting_failed', 0, $exception);
        }
    }

    public function delete(Asset $asset): void
    {
        $this->assertDeletable($asset);
        $this->assetManager->beginTransaction();

        try {
            $deleteId = (string) $asset->getId();

            $this->deleteWithFiles($asset);
            $this->indexManager->delete($asset, $deleteId);

            $this->assetManager->commit();

            $this->assetFileDeleteEventDispatcher->dispatchAll();
            $this->assetEventDispatcher->dispatchAll();
        } catch (Throwable $exception) {
            if ($this->assetManager->isTransactionActive()) {
                $this->assetManager->rollback();
            }

            throw new RuntimeException('asset_delete_failed', 0, $exception);
        }
    }

    /**
     * @param ReadableCollection<int, Asset> $assets
     *
     * @throws FilesystemException
     */
    public function deleteBulk(ReadableCollection $assets): int
    {
        if ($assets->isEmpty()) {
            return 0;
        }
        foreach ($assets as $asset) {
            $this->assertDeletable($asset);
            $deletedId = (string) $asset->getId();
            $this->deleteWithFiles($asset);
            $this->indexManager->delete($asset, $deletedId);
        }

        return $assets->count();
    }

    /**
     * @throws DependencyExistsException
     */
    private function assertDeletable(Asset $asset): void
    {
        if ($this->extSystemRepository->existsByTtsFreeAudioEpilogAsset($asset)) {
            throw (new DependencyExistsException())->addDependency(ExtSystem::class);
        }
    }

    /**
     * @throws FilesystemException
     */
    private function deleteWithFiles(Asset $asset): void
    {
        $deleteId = (string) $asset->getId();
        $deleteBy = $asset->getNotifyTo() ?: $asset->getCreatedBy();
        $this->assetManager->removeSibling($asset, false);

        foreach ($asset->getSlots() as $slot) {
            $this->deleteAssetFile($slot->getAssetFile(), $asset, $deleteId, $deleteBy);
        }

        // Superseded TTS audio keeps the asset FK but no slot; without this the `SET NULL` FK leaves a publicly routable orphan.
        foreach ($this->audioFileRepository->findDetachedByAsset($asset) as $audioFile) {
            $this->deleteAssetFile($audioFile, $asset, $deleteId, $deleteBy);
        }

        $this->assetEventDispatcher->addEvent($deleteId, $asset, $deleteBy);

        $this->assetManager->delete($asset);
        $this->fileStash->emptyAll();
    }

    private function deleteAssetFile(AssetFile $assetFile, Asset $asset, string $deleteId, DamUser $deleteBy): void
    {
        $this->assetFileDeleteEventDispatcher->addEvent(
            (string) $assetFile->getId(),
            $deleteId,
            $assetFile,
            $asset->getAttributes()->getAssetType(),
            $deleteBy,
        );
        $this->assetFileManagerProvider->getManager($assetFile)->delete($assetFile, false);
    }
}
