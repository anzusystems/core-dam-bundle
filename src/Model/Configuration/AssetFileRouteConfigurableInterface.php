<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

interface AssetFileRouteConfigurableInterface
{
    public function getPublicDomainName(): string;
    public function setPublicDomainName(string $publicDomainName): static;
}
