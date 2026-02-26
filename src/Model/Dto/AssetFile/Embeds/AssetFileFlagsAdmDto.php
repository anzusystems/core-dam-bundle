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

    #[Serialize]
    private bool $internal;

    #[Serialize]
    private bool $overrideInternal;

    public static function getInstance(AssetFileFlags $assetFileFlags): self
    {
        return (new self())
            ->setSingleUse($assetFileFlags->isSingleUse())
            ->setPublic($assetFileFlags->isPublic())
            ->setInternal($assetFileFlags->isInternal())
            ->setOverrideInternal($assetFileFlags->isOverrideInternal())
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

    public function isInternal(): bool
    {
        return $this->internal;
    }

    public function setInternal(bool $internal): self
    {
        $this->internal = $internal;

        return $this;
    }

    public function isOverrideInternal(): bool
    {
        return $this->overrideInternal;
    }

    public function setOverrideInternal(bool $overrideInternal): self
    {
        $this->overrideInternal = $overrideInternal;

        return $this;
    }
}
