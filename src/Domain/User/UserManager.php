<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\User;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\DamUser;

class UserManager extends AbstractManager
{
    public function create(DamUser $user, bool $flush = true): DamUser
    {
        $this->trackCreation($user);
        $this->entityManager->persist($user);
        $this->flush($flush);

        return $user;
    }
}
