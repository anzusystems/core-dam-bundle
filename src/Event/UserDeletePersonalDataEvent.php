<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

use AnzuSystems\CoreDamBundle\Entity\DamUser;

final class UserDeletePersonalDataEvent
{
    public function __construct(
        private readonly DamUser $user,
    ) {
    }

    public function getUser(): DamUser
    {
        return $this->user;
    }
}
