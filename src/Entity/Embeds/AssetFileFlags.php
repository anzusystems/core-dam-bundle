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

    public function __construct()
    {
        $this->setProcessedMetadata(false);
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
}
