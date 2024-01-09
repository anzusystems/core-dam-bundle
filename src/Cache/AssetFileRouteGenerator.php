<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Cache;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetFileRouteConfigurableInterface;

final readonly class AssetFileRouteGenerator
{
    public function __construct(
        private ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private ConfigurationProvider $configurationProvider,
    ) {
    }

    public function getFullUrl(
        AssetFileRoute $route
    ): string {
        if (false === $route->getUri()->isMain()) {
            return UrlHelper::concatPathWithDomain(
                $this->configurationProvider->getSettings()->getRedirectDomain(),
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
