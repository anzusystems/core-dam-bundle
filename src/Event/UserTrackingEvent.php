<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\CoreDamBundle\Entity\DamUser;

final class UserTrackingEvent
{
    public function __construct(
        private DamUser $user,
        private readonly UserTrackingInterface $object,
    ) {
    }

    public function getUser(): DamUser
    {
        return $this->user;
    }

    public function getObject(): UserTrackingInterface
    {
        return $this->object;
    }

    public function setUser(DamUser $user): self
    {
        $this->user = $user;

        return $this;
    }
}
