<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Validator\Constraints\UniqueEntity;
use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Repository\PermissionGroupRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use AnzuSystems\SerializerBundle\Metadata\ContainerParam;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PermissionGroupRepository::class)]
#[UniqueEntity(fields: ['title'])]
class PermissionGroup extends AnzuPermissionGroup
{
    use UserTrackingTrait;

    /**
     * List of users who belongs to permission group.
     */
    #[ORM\ManyToMany(targetEntity: DamUser::class, mappedBy: 'permissionGroups', indexBy: 'id')]
    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(DamUser::class))]
    protected Collection $users;
}
