<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class UnsplashAssetExternalProviderConfiguration extends AssetExternalProviderConfiguration
{
    public const string ACCESS_KEY = 'access_key';

    private string $accessKey;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setAccessKey($config[self::OPTIONS_KEY][self::ACCESS_KEY] ?? '')
        ;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): self
    {
        $this->accessKey = $accessKey;

        return $this;
    }
}
