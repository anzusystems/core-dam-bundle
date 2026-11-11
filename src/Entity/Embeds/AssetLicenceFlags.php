<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetLicenceFlags
{
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Serialize]
    private bool $manualUploadAllowed = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Serialize]
    private bool $directUseAllowed = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $singleUseEnforced = false;

    public function isManualUploadAllowed(): bool
    {
        return $this->manualUploadAllowed;
    }

    public function isNotManualUploadAllowed(): bool
    {
        return false === $this->isManualUploadAllowed();
    }

    public function setManualUploadAllowed(bool $manualUploadAllowed): self
    {
        $this->manualUploadAllowed = $manualUploadAllowed;

        return $this;
    }

    public function isDirectUseAllowed(): bool
    {
        return $this->directUseAllowed;
    }

    public function setDirectUseAllowed(bool $directUseAllowed): self
    {
        $this->directUseAllowed = $directUseAllowed;

        return $this;
    }

    public function isSingleUseEnforced(): bool
    {
        return $this->singleUseEnforced;
    }

    public function setSingleUseEnforced(bool $singleUseEnforced): self
    {
        $this->singleUseEnforced = $singleUseEnforced;

        return $this;
    }
}
