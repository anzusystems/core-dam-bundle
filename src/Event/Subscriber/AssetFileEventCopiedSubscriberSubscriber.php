<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Subscriber;

use AnzuSystems\CoreDamBundle\Event\AssetFileCopiedEvent;
use AnzuSystems\CoreDamBundle\Event\MetadataProcessedEvent;
use AnzuSystems\CoreDamBundle\Notification\AssetFileNotificationDispatcher;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class AssetFileEventCopiedSubscriberSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AssetFileNotificationDispatcher $dispatcher
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MetadataProcessedEvent::class => 'onAssetFileCopy',
        ];
    }

    /**
     * @throws SerializerException
     */
    public function onAssetFileCopy(AssetFileCopiedEvent $event): void
    {
        $this->dispatcher->notifyAssetFileCopied($event);
    }
}
