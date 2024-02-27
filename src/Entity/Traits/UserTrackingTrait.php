<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Traits;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use AnzuSystems\SerializerBundle\Metadata\ContainerParam;
use Doctrine\ORM\Mapping as ORM;

trait UserTrackingTrait
{
    #[ORM\ManyToOne(targetEntity: DamUser::class)]
    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(DamUser::class))]
    protected DamUser $createdBy;

    #[ORM\ManyToOne(targetEntity: DamUser::class)]
    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(DamUser::class))]
    protected DamUser $modifiedBy;

    /**
     * @return DamUser
     */
    public function getCreatedBy(): AnzuUser
    {
        return $this->createdBy;
    }

    public function setCreatedBy(AnzuUser|DamUser $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return DamUser
     */
    public function getModifiedBy(): AnzuUser
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(AnzuUser|DamUser $modifiedBy): static
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
    }
}
