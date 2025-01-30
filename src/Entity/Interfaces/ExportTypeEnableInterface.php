<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;

interface ExportTypeEnableInterface
{
    public function isWebPublicExportEnabled(): bool;
    public function isMobilePublicExportEnabled(): bool;
}
