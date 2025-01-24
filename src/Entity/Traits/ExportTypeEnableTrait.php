<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Traits;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait ExportTypeEnableTrait
{
    #[Serialize]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $webPublicExportEnabled = false;

    #[Serialize]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $mobilePublicExportEnabled = false;

    public function isWebPublicExportEnabled(): bool
    {
        return $this->webPublicExportEnabled;
    }

    public function setWebPublicExportEnabled(bool $webPublicExportEnabled): self
    {
        $this->webPublicExportEnabled = $webPublicExportEnabled;

        return $this;
    }

    public function isMobilePublicExportEnabled(): bool
    {
        return $this->mobilePublicExportEnabled;
    }

    public function setMobilePublicExportEnabled(bool $mobilePublicExportEnabled): self
    {
        $this->mobilePublicExportEnabled = $mobilePublicExportEnabled;

        return $this;
    }
}
