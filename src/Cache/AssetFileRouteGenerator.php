<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Cache;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetFileRouteConfigurableInterface;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;

final readonly class AssetFileRouteGenerator
{
    public function __construct(
        private ExtSystemConfigurationProvider $extSystemConfigurationProvider,
    ) {
    }

    public function getFullUrl(
        AssetFileRoute $route
    ): string {
        if ($route->getUri()->getSchemeAndHost()) {
            return UrlHelper::concatPathWithDomain(
                $route->getUri()->getSchemeAndHost(),
                $route->getUri()->getPath()
            );
        }

        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAsset(
            $route->getTargetAssetFile()->getAsset()
        );

        if ($config instanceof AssetFileRouteConfigurableInterface) {
            return UrlHelper::concatPathWithDomain(
                $config->getPublicDomainName(),
                $route->getUri()->getPath()
            );
        }

        return '';
    }
}
