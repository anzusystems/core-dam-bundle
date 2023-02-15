<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Traits;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait EventDispatcherAwareTrait
{
    private EventDispatcherInterface $dispatcher;

    #[Required]
    public function setDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }
}
