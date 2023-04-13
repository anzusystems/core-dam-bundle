<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\User;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Event\UserDeletePersonalDataEvent;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

class UserManager extends AbstractManager
{
    public function create(DamUser $user, bool $flush = true): DamUser
    {
        $this->trackCreation($user);
        $this->entityManager->persist($user);
        $this->flush($flush);

        return $user;
    }

    public function deletePersonalData(DamUser $user, bool $flush = true): DamUser
    {
        $this->trackModification($user);
        $user
            ->setEnabled(false)
            ->setAssetLicences(new ArrayCollection())
            ->setUserToExtSystems(new ArrayCollection())
            ->setPermissions([])
            ->setPermissionGroups(new ArrayCollection())
            ->setAllowedAssetExternalProviders([])
            ->setAllowedDistributionServices([])
            ->setEmail(sprintf('deleted_%s@adam.sme.sk', Uuid::v7()))
        ;
        $this->eventDispatcher->dispatch(new UserDeletePersonalDataEvent($user));
        $this->flush($flush);

        return $user;
    }
}
