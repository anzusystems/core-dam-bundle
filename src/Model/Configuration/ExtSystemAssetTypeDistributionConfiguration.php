<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemAssetTypeDistributionConfiguration
{
    public const string DISTRIBUTION_SERVICES_KEY = 'distribution_services';
    public const string DISTRIBUTION_REQUIREMENTS_KEY = 'distribution_requirements';

    public function __construct(
        private readonly array $distributionServices,
        private readonly array $distributionRequirements,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        return new self(
            $config[self::DISTRIBUTION_SERVICES_KEY] ?? [],
            false === empty($config[self::DISTRIBUTION_REQUIREMENTS_KEY])
                ? array_map(
                    fn (array $requirement): ExtSystemAssetTypeDistributionRequirementConfiguration => ExtSystemAssetTypeDistributionRequirementConfiguration::getFromArrayConfiguration($requirement),
                    $config[self::DISTRIBUTION_REQUIREMENTS_KEY]
                )
                : [],
        );
    }

    public function isInDistributionServices(string $serviceName): bool
    {
        return in_array($serviceName, $this->getDistributionServices(), true);
    }

    public function getDistributionServices(): array
    {
        return $this->distributionServices;
    }

    /**
     * @return array<string, ExtSystemAssetTypeDistributionRequirementConfiguration>
     */
    public function getDistributionRequirements(): array
    {
        return $this->distributionRequirements;
    }

    public function getDistributionRequirementForServiceName(
        string $serviceName,
    ): ExtSystemAssetTypeDistributionRequirementConfiguration {
        return $this->getDistributionRequirements()[$serviceName];
    }
}
