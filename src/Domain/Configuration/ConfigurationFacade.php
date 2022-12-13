<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Configuration;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Decorator\ExtSystemAdmGetDecorator;
use AnzuSystems\CoreDamBundle\Model\Dto\Configuration\ConfigurationAdmGetDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Configuration\ConfigurationPubGetDto;

final class ConfigurationFacade
{
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly DistributionConfigurationProvider $distributionConfigurationProvider,
        private readonly AssetExternalProviderConfigurationProvider $assetExternalProviderConfigurationProvider,
    ) {
    }

    public function decorateAdm(): ConfigurationAdmGetDto
    {
        $decorator = $this->configurationProvider->getSettings();

        return ConfigurationAdmGetDto::getInstance(
            $decorator,
            $this->configurationProvider->getColorSet(),
            $this->assetExternalProviderConfigurationProvider->getAssetExternalProviders(),
            $this->distributionConfigurationProvider->getDistributionServices()
        );
    }

    public function decoratePub(): ConfigurationPubGetDto
    {
        $decorator = $this->configurationProvider->getSettings();

        return ConfigurationPubGetDto::getInstance(
            $decorator
        );
    }

    public function decorateExtSystemAdm(ExtSystem $extSystem): ExtSystemAdmGetDecorator
    {
        return ExtSystemAdmGetDecorator::getInstance(
            $this->extSystemConfigurationProvider->getExtSystemConfiguration($extSystem->getSlug())
        );
    }
}
