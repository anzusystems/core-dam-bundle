<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Notification;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Event\DistributionAuthorized;
use AnzuSystems\CoreDamBundle\Event\DistributionStatusEvent;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\DistributionAdmNotificationDecorator;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\DistributionAuthorizedAdmNotificationDecorator;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final class DistributionNotificationDispatcher extends AbstractNotificationDispatcher
{
    use SerializerAwareTrait;
    private const EVENT_NAME_PREFIX = 'distribution_';
    private const EVENT_DISTRIBUTION_AUTHORIZED = 'distribution_authorized';

    /**
     * @throws SerializerException
     */
    public function notifyStatusChange(DistributionStatusEvent $event): void
    {
        if (null === $event->getDistribution()->getNotifyTo()) {
            return;
        }

        $this->notify(
            [(int) $event->getDistribution()->getNotifyTo()->getId()],
            self::EVENT_NAME_PREFIX . $event->getDistribution()->getStatus()->toString(),
            DistributionAdmNotificationDecorator::getInstance($event->getDistribution())
        );
    }

    /**
     * @throws SerializerException
     */
    public function notifyDistributionAuthorized(DistributionAuthorized $event): void
    {
        $this->notify(
            [$event->getTargetUserId()],
            self::EVENT_DISTRIBUTION_AUTHORIZED,
            DistributionAuthorizedAdmNotificationDecorator::getInstance($event)
        );
    }
}
