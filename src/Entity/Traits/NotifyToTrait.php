<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Traits;

use AnzuSystems\CoreDamBundle\Entity\DamUser;
use Doctrine\ORM\Mapping as ORM;

trait NotifyToTrait
{
    #[ORM\ManyToOne(targetEntity: DamUser::class)]
    protected ?DamUser $notifyTo = null;

    public function getNotifyTo(): ?DamUser
    {
        return $this->notifyTo;
    }

    public function setNotifyTo(?DamUser $notifyTo): self
    {
        $this->notifyTo = $notifyTo;

        return $this;
    }
}
