<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Subscriber;

use AnzuSystems\CoreDamBundle\Event\AssetFileChangeStateEvent;
use AnzuSystems\CoreDamBundle\Messenger\Message\AssetRefreshPropertiesMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Notification\AssetFileNotificationDispatcher;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AssetFileEventSubscriber implements EventSubscriberInterface
{
    use MessageBusAwareTrait;

    public function __construct(
        private readonly AssetFileNotificationDispatcher $dispatcher
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AssetFileChangeStateEvent::class => 'onAssetChangeState',
        ];
    }

    /**
     * @throws SerializerException
     */
    public function onAssetChangeState(AssetFileChangeStateEvent $event): void
    {
        $this->dispatcher->notifyAssetFileChanged($event);

        if ($event->getAsset()->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Duplicate)) {
            $this->messageBus->dispatch(new AssetRefreshPropertiesMessage((string) $event->getAsset()->getId()));
        }
    }
}
