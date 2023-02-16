<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Configuration;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\InvalidExtSystemConfigurationException;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeDistributionRequirementConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAudioTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemImageTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemVideoTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final class ExtSystemConfigurationProvider
{
    /**
     * @var array<string, ExtSystemConfiguration>
     */
    private array $extSystemsCache = [];

    public function __construct(
        private readonly array $extSystems,
    ) {
    }

    public function getExtSystemConfigurationByAssetFile(AssetFile $asset): ExtSystemAssetTypeConfiguration|ExtSystemImageTypeConfiguration|ExtSystemAudioTypeConfiguration|ExtSystemVideoTypeConfiguration
    {
        return $this->getExtSystemConfigurationByAsset($asset->getAsset());
    }

    public function getDistributionRequirements(
        ExtSystemAssetTypeConfiguration $configuration,
        string $distributionId
    ): ExtSystemAssetTypeDistributionRequirementConfiguration {
        if (isset($configuration->getDistribution()->getDistributionRequirements()[$distributionId])) {
            return $configuration->getDistribution()->getDistributionRequirements()[$distributionId];
        }

        throw new InvalidExtSystemConfigurationException(InvalidExtSystemConfigurationException::ERROR_MESSAGE);
    }

    public function getExtSystemConfigurationByAsset(Asset $asset): ExtSystemAssetTypeConfiguration|ExtSystemImageTypeConfiguration|ExtSystemAudioTypeConfiguration|ExtSystemVideoTypeConfiguration
    {
        return $this->getExtSystemConfigurationByAssetType(
            $asset->getAttributes()->getAssetType(),
            $asset->getLicence()->getExtSystem()->getSlug()
        );
    }

    public function getAudioExtSystemConfiguration(string $extSystemSlug): ExtSystemAudioTypeConfiguration
    {
        $configuration = $this->getExtSystemConfigurationByAssetType(AssetType::Audio, $extSystemSlug);
        if ($configuration instanceof ExtSystemAudioTypeConfiguration) {
            return $configuration;
        }

        throw new InvalidExtSystemConfigurationException(InvalidExtSystemConfigurationException::ERROR_MESSAGE);
    }

    public function getImageExtSystemConfiguration(string $extSystemSlug): ExtSystemImageTypeConfiguration
    {
        $configuration = $this->getExtSystemConfigurationByAssetType(AssetType::Audio, $extSystemSlug);
        if ($configuration instanceof ExtSystemImageTypeConfiguration) {
            return $configuration;
        }

        throw new InvalidExtSystemConfigurationException(InvalidExtSystemConfigurationException::ERROR_MESSAGE);
    }

    public function getExtSystemConfigurationByAssetType(AssetType $assetType, string $extSystemSlug): ExtSystemAssetTypeConfiguration|ExtSystemImageTypeConfiguration|ExtSystemAudioTypeConfiguration|ExtSystemVideoTypeConfiguration
    {
        $configuration = $this->getExtSystemConfiguration($extSystemSlug);

        return match ($assetType) {
            AssetType::Image => $configuration->getImage(),
            AssetType::Video => $configuration->getVideo(),
            AssetType::Audio => $configuration->getAudio(),
            AssetType::Document => $configuration->getDocument(),
        };
    }

    public function getExtSystemSlugs(): array
    {
        return array_keys($this->extSystems);
    }

    /**
     * @throws InvalidExtSystemConfigurationException
     */
    public function getExtSystemConfiguration(string $slug): ExtSystemConfiguration
    {
        if (isset($this->extSystemsCache[$slug])) {
            return $this->extSystemsCache[$slug];
        }

        if (isset($this->extSystems[$slug])) {
            $configuration = ExtSystemConfiguration::getFromArrayConfiguration($this->extSystems[$slug]);
            $this->extSystemsCache[$slug] = $configuration;

            return $configuration;
        }

        throw new InvalidExtSystemConfigurationException(InvalidExtSystemConfigurationException::ERROR_MESSAGE);
    }

    /**
     * @throws InvalidExtSystemConfigurationException
     */
    public function getAssetConfiguration(string $slug, AssetType $assetType): ExtSystemAssetTypeConfiguration
    {
        $configuration = $this->getExtSystemConfiguration($slug);

        return match ($assetType) {
            AssetType::Image => $configuration->getImage(),
            AssetType::Video => $configuration->getVideo(),
            AssetType::Audio => $configuration->getAudio(),
            AssetType::Document => $configuration->getDocument(),
        };
    }
}
