<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Notification;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Event\AssetDeleteEvent;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetFile\AssetAdmNotificationDecorator;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final class AssetNotificationDispatcher extends AbstractNotificationDispatcher
{
    use SerializerAwareTrait;
    private const EVENT_ASSET_DELETED_NAME = 'asset_deleted';

    /**
     * @throws SerializerException
     */
    public function notifyAssetDeleted(AssetDeleteEvent $event): void
    {
        $this->notify(
            [$event->getDeletedBy()->getId()],
            self::EVENT_ASSET_DELETED_NAME,
            AssetAdmNotificationDecorator::getInstance($event->getDeleteId())
        );
    }
}
