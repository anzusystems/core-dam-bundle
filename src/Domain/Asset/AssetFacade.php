<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManagerProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetEventDispatcher;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileDeleteEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Traits\EntityValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Traits\FileStashAwareTrait;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

class AssetFacade
{
    use FileStashAwareTrait;
    use IndexManagerAwareTrait;
    use EntityValidatorAwareTrait;

    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly AssetFactory $assetFactory,
        private readonly MessageBusInterface $messageBus,
        private readonly AssetStatusManager $assetStatusManager,
        private readonly AssetFileManagerProvider $assetFileManagerProvider,
        private readonly AssetEventDispatcher $assetEventDispatcher,
        private readonly AssetFileDeleteEventDispatcher $assetFileDeleteEventDispatcher,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function update(Asset $asset, AssetAdmUpdateDto $newAssetDto): Asset
    {
        $this->entityValidator->validateDto($newAssetDto, $asset);

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
        $this->entityValidator->validateDto($assetAdmCreateDto);
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

    public function toDeleting(Asset $asset): void
    {
        try {
            $this->assetManager->beginTransaction();
            $this->assetManager->setNotifyTo($asset);
            $this->assetStatusManager->toDeleting($asset);
            $this->assetManager->commit();

            $this->messageBus->dispatch(new AssetChangeStateMessage($asset));
        } catch (Throwable $exception) {
            $this->assetManager->rollback();

            throw new RuntimeException('asset_deleting_failed', 0, $exception);
        }
    }

    public function delete(Asset $asset): void
    {
        try {
            $this->assetManager->beginTransaction();
            $deleteId = (string) $asset->getId();
            $deleteBy = $asset->getNotifyTo();

            foreach ($asset->getSlots() as $slot) {
                $assetFile = $slot->getAssetFile();
                $assetFile->setAsset(new Asset());
                $this->assetFileDeleteEventDispatcher->addEvent(
                    (string) $assetFile->getId(),
                    $deleteId,
                    $assetFile,
                    $asset->getAttributes()->getAssetType(),
                    $deleteBy,
                );
                $manager = $this->assetFileManagerProvider->getManager($assetFile);
                $manager->delete($assetFile, false);
            }

            $this->assetManager->delete($asset);
            $this->indexManager->delete($asset, $deleteId);
            $this->fileStash->emptyAll();

            $this->assetManager->commit();

            $this->assetFileDeleteEventDispatcher->dispatchAll();
            $this->assetEventDispatcher->dispatchAssetDelete($deleteId, $asset, $deleteBy);
        } catch (Throwable $exception) {
            $this->assetManager->rollback();

            throw new RuntimeException('asset_delete_failed', 0, $exception);
        }
    }
}
