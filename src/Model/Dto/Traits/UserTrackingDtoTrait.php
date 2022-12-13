<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Traits;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

trait UserTrackingDtoTrait
{
    protected DamUser $createdBy;
    protected DamUser $modifiedBy;

    #[Serialize(handler: EntityIdHandler::class)]
    public function getCreatedBy(): DamUser
    {
        return $this->createdBy;
    }

    public function setCreatedBy(AnzuUser|DamUser $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    #[Serialize(handler: EntityIdHandler::class)]
    public function getModifiedBy(): DamUser
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(AnzuUser|DamUser $modifiedBy): self
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
    }
}
