<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto;

use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\CoreDamBundle\Model\Dto\Traits\TimeTrackingDtoTrait;
use AnzuSystems\CoreDamBundle\Model\Dto\Traits\UserTrackingDtoTrait;
use AnzuSystems\CoreDamBundle\Model\Dto\Traits\UuidIdentityDtoTrait;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

abstract class AbstractEntityDto
{
    use UuidIdentityDtoTrait;
    use TimeTrackingDtoTrait;
    use UserTrackingDtoTrait;

    protected string $resourceName = self::class;

    public static function getBaseInstance(
        object $entity
    ): static {
        $entityDto = (new static());

        if ($entity instanceof UuidIdentifiableInterface) {
            $entityDto->setId($entity->getId());
        }
        if ($entity instanceof TimeTrackingInterface) {
            $entityDto
                ->setCreatedAt($entity->getCreatedAt())
                ->setModifiedAt($entity->getModifiedAt());
        }
        if ($entity instanceof UserTrackingInterface) {
            $entityDto
                ->setCreatedBy($entity->getCreatedBy())
                ->setModifiedBy($entity->getModifiedBy());
        }
        $entityDto->resourceName = $entity::class;

        return $entityDto;
    }

    public function setResourceName(string $resourceName): static
    {
        $this->resourceName = $resourceName;

        return $this;
    }

    #[Serialize(serializedName: '_resourceName')]
    public function getResourceName(): string
    {
        return lcfirst(substr((string) strrchr($this->resourceName, '\\'), 1));
    }

    #[Serialize(serializedName: '_system')]
    public static function getSystem(): string
    {
        return AnzuApp::getAppSystem();
    }
}
