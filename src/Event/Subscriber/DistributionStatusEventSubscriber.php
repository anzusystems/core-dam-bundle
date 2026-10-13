<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Subscriber;

use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Event\DistributionStatusEvent;
use AnzuSystems\CoreDamBundle\Messenger\Message\MediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Notification\DistributionNotificationDispatcher;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DistributionStatusEventSubscriber implements EventSubscriberInterface
{
    use MessageBusAwareTrait;

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

        $distribution = $event->getDistribution();
        $assetFile = $distribution->getAssetFile();
        if (
            $event->getStatus()->is(DistributionProcessStatus::Failed)
            && $distribution instanceof JwDistribution
            && null !== $assetFile
            && $assetFile->getAssetAttributes()->getCreateStrategy()->is(AssetFileCreateStrategy::Download)
        ) {
            $this->messageBus->dispatch(new MediaStatusCallbackMessage(
                extSystemId: $assetFile->getExtSystem()->getId(),
                assetId: (string) $assetFile->getAsset()->getId(),
                status: MediaStatusType::VideoFailed,
                failureReason: MediaStatusCallbackMessage::FAILURE_REASON_DISTRIBUTION_FAILED,
            ));
        }
    }
}
