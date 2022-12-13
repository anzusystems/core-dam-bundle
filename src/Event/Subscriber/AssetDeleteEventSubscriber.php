<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Subscriber;

use AnzuSystems\CoreDamBundle\Event\AssetDeleteEvent;
use AnzuSystems\CoreDamBundle\Notification\AssetNotificationDispatcher;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AssetDeleteEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AssetNotificationDispatcher $notificationDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AssetDeleteEvent::class => 'deleteAssetFile',
        ];
    }

    /**
     * @throws SerializerException
     */
    public function deleteAssetFile(AssetDeleteEvent $event): void
    {
        $this->notificationDispatcher->notifyAssetDeleted($event);
    }
}
