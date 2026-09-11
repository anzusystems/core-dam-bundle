<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

/**
 * One photo the sync refused to claim, together with who holds it now. The holder is reported, never the
 * scope — the caller shows the user which article blocks the photo, not which row it came from.
 */
final class ImageUsageConflictDto
{
    #[Serialize]
    private string $damId = App::EMPTY_STRING;

    #[Serialize]
    private string $resourceName = App::EMPTY_STRING;

    #[Serialize]
    private string $resourceId = App::EMPTY_STRING;

    public static function getInstance(string $damId, AssetFileAttributes $holder): self
    {
        return (new self())
            ->setDamId($damId)
            ->setResourceName($holder->getUsedByResourceName())
            ->setResourceId($holder->getUsedByResourceId())
        ;
    }

    public function getDamId(): string
    {
        return $this->damId;
    }

    public function setDamId(string $damId): self
    {
        $this->damId = $damId;

        return $this;
    }

    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    public function setResourceName(string $resourceName): self
    {
        $this->resourceName = $resourceName;

        return $this;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    public function setResourceId(string $resourceId): self
    {
        $this->resourceId = $resourceId;

        return $this;
    }
}
