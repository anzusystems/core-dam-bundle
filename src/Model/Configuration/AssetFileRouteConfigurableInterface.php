<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

interface AssetFileRouteConfigurableInterface
{
    public function getPublicDomain(): string;
    public function setPublicDomain(string $publicDomainName): static;
}
