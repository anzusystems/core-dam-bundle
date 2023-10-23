<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileFlags;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetFileFlagsAdmDto
{
    #[Serialize]
    private bool $public;

    #[Serialize]
    private bool $singleUse;

    public static function getInstance(AssetFileFlags $assetFileFlags): self
    {
        return (new self())
            ->setSingleUse($assetFileFlags->isSingleUse())
            ->setPublic($assetFileFlags->isPublic())
        ;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }

    public function isSingleUse(): bool
    {
        return $this->singleUse;
    }

    public function setSingleUse(bool $singleUse): self
    {
        $this->singleUse = $singleUse;

        return $this;
    }
}
