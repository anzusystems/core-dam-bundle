<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Configuration;

use AnzuSystems\CoreDamBundle\Distribution\Modules\JwPlayerDistributionModule;
use AnzuSystems\CoreDamBundle\Distribution\Modules\YoutubeDistributionModule;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\Configuration\DistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\JwDistributionServiceConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\YoutubeDistributionServiceConfiguration;

final class DistributionConfigurationProvider
{
    /**
     * @param array<int, DistributionServiceConfiguration> $distributionServicesCache
     */
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly array $distributionServices,
        private array $distributionServicesCache = [],
    ) {
    }

    /**
     * @throws DomainException
     */
    public function getAuthorizedRedirectUrl(string $serviceName): string
    {
        $config = $this->getDistributionService($serviceName);

        return $config->getAuthRedirectUrlKey() ??
            $this->configurationProvider->getSettings()->getDistributionAuthRedirectUrl();
    }

    /**
     * @return array<string, DistributionServiceConfiguration>
     */
    public function getDistributionServices(): array
    {
        $distributionServiceConfiguration = [];
        foreach ($this->distributionServices as $serviceName => $distributionServiceConfig) {
            $distributionServiceConfiguration[$serviceName] = $this->distributionServicesCache[$serviceName] ??= $this->createDistributionServiceConfiguration(
                $serviceName,
                $distributionServiceConfig
            );
        }

        return $distributionServiceConfiguration;
    }

    /**
     * @throws DomainException
     */
    public function getDistributionService(string $serviceName): DistributionServiceConfiguration
    {
        if (false === isset($this->distributionServicesCache[$serviceName])) {
            if (false === isset($this->distributionServices[$serviceName])) {
                throw new DomainException("Invalid distribution service ({$serviceName})");
            }

            $this->distributionServicesCache[$serviceName] = $this->createDistributionServiceConfiguration(
                $serviceName,
                $this->distributionServices[$serviceName]
            );
        }

        return $this->distributionServicesCache[$serviceName];
    }

    /**
     * @throws DomainException
     */
    public function getJwDistributionService(string $serviceName): JwDistributionServiceConfiguration
    {
        $service = $this->getDistributionService($serviceName);
        if ($service instanceof JwDistributionServiceConfiguration) {
            return $service;
        }

        throw new DomainException("Distribution service ({$serviceName}) type is invalid");
    }

    /**
     * @throws DomainException
     */
    public function getYoutubeDistributionService(string $serviceName): YoutubeDistributionServiceConfiguration
    {
        $service = $this->getDistributionService($serviceName);
        if ($service instanceof YoutubeDistributionServiceConfiguration) {
            return $service;
        }

        throw new DomainException("Distribution service ({$serviceName}) type is invalid");
    }

    private function createDistributionServiceConfiguration(string $serviceName, array $config): DistributionServiceConfiguration
    {
        $moduleKey = $config[DistributionServiceConfiguration::MODULE_KEY] ?? '';
        if (JwPlayerDistributionModule::class === $moduleKey) {
            return JwDistributionServiceConfiguration::getFromArrayConfiguration($config)->setServiceId($serviceName);
        }
        if (YoutubeDistributionModule::class === $moduleKey) {
            return YoutubeDistributionServiceConfiguration::getFromArrayConfiguration($config)->setServiceId($serviceName);
        }

        return DistributionServiceConfiguration::getFromArrayConfiguration($config)->setServiceId($serviceName);
    }
}
