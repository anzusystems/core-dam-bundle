<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

/**
 * One photo a claim was refused for, together with who holds it now.
 */
final class ImageUsageConflictDto
{
    #[Serialize]
    private string $damId = App::EMPTY_STRING;

    #[Serialize]
    private string $holderName = App::EMPTY_STRING;

    #[Serialize]
    private string $holderId = App::EMPTY_STRING;

    public static function getInstance(string $damId, AssetFileAttributes $holder): self
    {
        return (new self())
            ->setDamId($damId)
            ->setHolderName($holder->getUsedByHolderName())
            ->setHolderId($holder->getUsedByHolderId())
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

    public function getHolderName(): string
    {
        return $this->holderName;
    }

    public function setHolderName(string $holderName): self
    {
        $this->holderName = $holderName;

        return $this;
    }

    public function getHolderId(): string
    {
        return $this->holderId;
    }

    public function setHolderId(string $holderId): self
    {
        $this->holderId = $holderId;

        return $this;
    }
}
