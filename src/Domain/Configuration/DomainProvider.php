<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Configuration;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetFileRouteConfigurableInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class DomainProvider
{
    public function __construct(
        private RequestStack $requestStack,
        private ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private string $redirectDomain,
    ) {
    }

    public function getSchemeAndHost(?string $domain = null): string
    {
        return $domain ?? $this->requestStack->getMainRequest()?->getSchemeAndHttpHost() ?? '';
    }

    public function domainAndHostEquals(string $schemeAndHost): bool
    {
        return $schemeAndHost === $this->getSchemeAndHost($schemeAndHost);
    }

    public function isCurrentSchemeAndHostRedirectDomain(): bool
    {
        return $this->domainAndHostEquals($this->redirectDomain);
    }

    public function isCurrentSchemeAndHostPublicDomain(AssetFile $assetFile): bool
    {
        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAssetFile($assetFile);

        if (false === ($config instanceof AssetFileRouteConfigurableInterface)) {
            return false;
        }

        return $this->domainAndHostEquals($config->getPublicDomain());
    }
}
