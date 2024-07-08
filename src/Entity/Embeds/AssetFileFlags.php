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

    public function __construct()
    {
        $this->setProcessedMetadata(false);
        $this->setPublic(true);
        $this->setSingleUse(false);
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
}
