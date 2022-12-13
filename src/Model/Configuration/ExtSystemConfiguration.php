<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\Contracts\Exception\AnzuException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final class ExtSystemConfiguration
{
    public const ID_KEY = 'id';
    public const ASSET_EXTERNAL_PROVIDERS_KEY = 'asset_external_providers';

    public function __construct(
        private readonly int $id,
        private readonly array $assetExternalProviders,
        private readonly ExtSystemAudioTypeConfiguration $audio,
        private readonly ExtSystemAssetTypeConfiguration $video,
        private readonly ExtSystemImageTypeConfiguration $image,
        private readonly ExtSystemAssetTypeConfiguration $document,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        return new self(
            $config[self::ID_KEY] ?? 0,
            array_map(
                static fn (array $config) => ExtSystemAssetExternalProviderConfiguration::getFromArrayConfiguration($config),
                $config[self::ASSET_EXTERNAL_PROVIDERS_KEY] ?? [],
            ),
            ExtSystemAudioTypeConfiguration::getFromArrayConfiguration($config[AssetType::Audio->toString()] ?? []),
            ExtSystemAssetTypeConfiguration::getFromArrayConfiguration($config[AssetType::Video->toString()] ?? []),
            ExtSystemImageTypeConfiguration::getFromArrayConfiguration($config[AssetType::Image->toString()] ?? []),
            ExtSystemAssetTypeConfiguration::getFromArrayConfiguration($config[AssetType::Document->toString()] ?? []),
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return list<ExtSystemAssetExternalProviderConfiguration>
     */
    public function getAssetExternalProviders(): array
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

    public function getDocument(): ExtSystemAssetTypeConfiguration
    {
        return $this->document;
    }

    public function getByAssetType(AssetType $type): ExtSystemAssetTypeConfiguration
    {
        return match ($type) {
            AssetType::Audio => $this->getAudio(),
            AssetType::Video => $this->getVideo(),
            AssetType::Image => $this->getImage(),
            AssetType::Document => $this->getDocument(),
            default => new AnzuException(sprintf('Unrecognized asset type "%s".', $type->toString())),
        };
    }
}
