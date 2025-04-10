<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Listener;

use AnzuSystems\CommonBundle\Event\JobCompletedEvent;
use AnzuSystems\CommonBundle\Event\JobErrorEvent;
use AnzuSystems\CommonBundle\Event\JobEvents;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: JobEvents::COMPLETED, method: 'onJobCompleted')]
#[AsEventListener(event: JobEvents::ERROR, method: 'onJobError')]
final readonly class JobListener
{
    public function __construct(
        private ExtSystemCallbackFacade $extSystemCallbackFacade,
    ) {
    }

    public function onJobCompleted(JobCompletedEvent $jobEvent): void
    {
        $job = $jobEvent->getJob();
        if ($job instanceof JobImageCopy) {
            $this->extSystemCallbackFacade->notifyFinishedJobImageCopy($job);
        }
    }

    public function onJobError(JobErrorEvent $jobEvent): void
    {
        $job = $jobEvent->getJob();
        if ($job instanceof JobImageCopy) {
            $this->extSystemCallbackFacade->notifyFinishedJobImageCopy($job);
        }
    }
}
