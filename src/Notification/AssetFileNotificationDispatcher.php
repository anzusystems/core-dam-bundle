<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Notification;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Event\AssetFileChangeStateEvent;
use AnzuSystems\CoreDamBundle\Event\AssetFileDeleteEvent;
use AnzuSystems\CoreDamBundle\Event\MetadataProcessedEvent;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetFile\AsseFileAdmNotificationDecorator;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetFile\AssetFileStatusAdmNotificationDecorator;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final class AssetFileNotificationDispatcher extends AbstractNotificationDispatcher
{
    use SerializerAwareTrait;

    private const string EVENT_NAME_PREFIX = 'asset_file_';
    private const string EVENT_METADATA_PROCESSED_NAME = 'asset_metadata_processed';
    private const string EVENT_ASSET_FILE_DELETED_NAME = 'asset_file_deleted';

    public function __construct(
        private readonly DamLogger $damLogger,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function notifyAssetFileDeleted(AssetFileDeleteEvent $event): void
    {
        $this->notify(
            [(int) $event->getDeletedBy()->getId()],
            self::EVENT_ASSET_FILE_DELETED_NAME,
            AsseFileAdmNotificationDecorator::getBaseInstance($event->getDeleteAssetId(), $event->getDeleteId())
        );
    }

    /**
     * @throws SerializerException
     */
    public function notifyAssetFileChanged(AssetFileChangeStateEvent $event): void
    {
        if (null === $event->getAsset()->getNotifyTo()) {
            return;
        }

        $this->notify(
            [(int) $event->getAsset()->getNotifyTo()->getId()],
            self::EVENT_NAME_PREFIX . $event->getAsset()->getAssetAttributes()->getStatus()->toString(),
            AssetFileStatusAdmNotificationDecorator::getInstance($event->getAsset())
        );
    }

    /**
     * @throws SerializerException
     */
    public function notifyMetadataProcessed(MetadataProcessedEvent $event): void
    {
        if (null === $event->getAsset()->getNotifyTo()) {
            return;
        }
        $this->notify(
            [(int) $event->getAsset()->getNotifyTo()->getId()],
            self::EVENT_METADATA_PROCESSED_NAME,
            AssetFileStatusAdmNotificationDecorator::getInstance($event->getAsset())
        );
    }
}
