<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetFileFlags
{
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $processedMetadata;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $public;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $singleUse;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $internal;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $overrideInternal;

    public function __construct()
    {
        $this->setProcessedMetadata(false);
        $this->setPublic(true);
        $this->setSingleUse(false);
        $this->setInternal(true);
        $this->setOverrideInternal(false);
    }

    public function isProcessedMetadata(): bool
    {
        return $this->processedMetadata;
    }

    public function setProcessedMetadata(bool $processedMetadata): self
    {
        $this->processedMetadata = $processedMetadata;

        return $this;
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
