<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\Contracts\Exception\AnzuException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Doctrine\Common\Collections\ArrayCollection;

final class ExtSystemConfiguration
{
    public const ID_KEY = 'id';
    public const ASSET_EXTERNAL_PROVIDERS_KEY = 'asset_external_providers';

    public function __construct(
        private readonly int $id,
        /** @var ArrayCollection<string, ExtSystemAssetExternalProviderConfiguration> */
        private readonly ArrayCollection $assetExternalProviders,
        private readonly ExtSystemAudioTypeConfiguration $audio,
        private readonly ExtSystemAssetTypeConfiguration $video,
        private readonly ExtSystemImageTypeConfiguration $image,
        private readonly ExtSystemDocumentTypeConfiguration $document,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        /** @var ArrayCollection<string, ExtSystemAssetExternalProviderConfiguration> $assetExternalProviders */
        $assetExternalProviders = new ArrayCollection();
        foreach ($config[self::ASSET_EXTERNAL_PROVIDERS_KEY] ?? [] as $providerConfig) {
            $provider = ExtSystemAssetExternalProviderConfiguration::getFromArrayConfiguration($providerConfig);
            $assetExternalProviders->set($provider->getProviderName(), $provider);
        }

        return new self(
            $config[self::ID_KEY] ?? 0,
            $assetExternalProviders,
            ExtSystemAudioTypeConfiguration::getFromArrayConfiguration($config[AssetType::Audio->toString()] ?? []),
            ExtSystemVideoTypeConfiguration::getFromArrayConfiguration($config[AssetType::Video->toString()] ?? []),
            ExtSystemImageTypeConfiguration::getFromArrayConfiguration($config[AssetType::Image->toString()] ?? []),
            ExtSystemDocumentTypeConfiguration::getFromArrayConfiguration($config[AssetType::Document->toString()] ?? []),
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return ArrayCollection<string, ExtSystemAssetExternalProviderConfiguration>
     */
    public function getAssetExternalProviders(): ArrayCollection
    {
        return $this->assetExternalProviders;
    }

    public function getAudio(): ExtSystemAudioTypeConfiguration
    {
        return $this->audio;
    }

    public function getVideo(): ExtSystemAssetTypeConfiguration
    {
        return $this->video;
    }

    public function getImage(): ExtSystemImageTypeConfiguration
    {
        return $this->image;
    }

    public function getDocument(): ExtSystemDocumentTypeConfiguration
    {
        return $this->document;
    }

    /**
     * @throws AnzuException
     */
    public function getByAssetType(AssetType $type): ExtSystemAssetTypeConfiguration
    {
        return match ($type) {
            AssetType::Audio => $this->getAudio(),
            AssetType::Video => $this->getVideo(),
            AssetType::Image => $this->getImage(),
            AssetType::Document => $this->getDocument(),
            default => throw new AnzuException(sprintf('Unrecognized asset type "%s".', $type->toString())),
        };
    }
}
