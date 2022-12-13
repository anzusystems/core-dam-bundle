<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Subscriber;

use AnzuSystems\CoreDamBundle\Event\DistributionStatusEvent;
use AnzuSystems\CoreDamBundle\Notification\DistributionNotificationDispatcher;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DistributionStatusEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly DistributionNotificationDispatcher $notificationDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DistributionStatusEvent::class => 'distributionStatusEvent',
        ];
    }

    /**
     * @throws SerializerException
     */
    public function distributionStatusEvent(DistributionStatusEvent $event): void
    {
        $this->notificationDispatcher->notifyStatusChange($event);
    }
}
