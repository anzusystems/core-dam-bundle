<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event\Subscriber;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CommandOutputSubscriber implements EventSubscriberInterface
{
    use OutputUtilTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'setOutput',
        ];
    }

    public function setOutput(ConsoleCommandEvent $event): void
    {
        $this->outputUtil->setOutput($event->getOutput());
    }
}
