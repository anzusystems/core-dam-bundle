<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Traits;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use AnzuSystems\SerializerBundle\Metadata\ContainerParam;

trait UserTrackingDtoTrait
{
    protected AnzuUser $createdBy;
    protected AnzuUser $modifiedBy;

    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(DamUser::class))]
    public function getCreatedBy(): AnzuUser
    {
        return $this->createdBy;
    }

    public function setCreatedBy(AnzuUser $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(AnzuUser::class))]
    public function getModifiedBy(): AnzuUser
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(AnzuUser $modifiedBy): static
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
    }
}
