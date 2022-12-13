<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

use AnzuSystems\CoreDamBundle\Entity\DamUser;

interface NotifiableInterface
{
    public function setNotifyTo(?DamUser $notifyTo): self;
}
