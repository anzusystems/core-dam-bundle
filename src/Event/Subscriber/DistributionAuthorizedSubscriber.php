<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Subscriber;

use AnzuSystems\CoreDamBundle\Event\DistributionAuthorized;
use AnzuSystems\CoreDamBundle\Notification\DistributionNotificationDispatcher;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DistributionAuthorizedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly DistributionNotificationDispatcher $dispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DistributionAuthorized::class => 'onDistributionAuthorized',
        ];
    }

    /**
     * @throws SerializerException
     */
    public function onDistributionAuthorized(DistributionAuthorized $event): void
    {
        $this->dispatcher->notifyDistributionAuthorized($event);
    }
}
