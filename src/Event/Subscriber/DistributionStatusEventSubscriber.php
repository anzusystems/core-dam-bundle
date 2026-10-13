<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Subscriber;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Event\DistributionStatusEvent;
use AnzuSystems\CoreDamBundle\Messenger\Message\MediaStatusCallbackMessage;
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
        // An existing extId means the video already reached JW, so a later failure is not a failed video for the ext system.
        if (
            $event->getStatus()->is(DistributionProcessStatus::Failed)
            && $distribution instanceof JwDistribution
            && $assetFile instanceof VideoFile
            && $assetFile->isCreatedByDownload()
            && App::EMPTY_STRING === $distribution->getExtId()
        ) {
            $this->messageBus->dispatch(MediaStatusCallbackMessage::fromVideoFile(
                $assetFile,
                MediaStatusType::VideoFailed,
                MediaStatusCallbackMessage::FAILURE_REASON_DISTRIBUTION_FAILED,
            ));
        }
    }
}
