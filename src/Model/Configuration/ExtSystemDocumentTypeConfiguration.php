<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemDocumentTypeConfiguration extends ExtSystemAssetTypeConfiguration implements AssetFileRouteConfigurableInterface
{
    public const string DOCUMENT_PUBLIC_STORAGE = 'public_storage';
    public const string PUBLIC_DOMAIN_NAME = 'public_domain_name';

    private string $publicStorage;
    private string $publicDomain;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setPublicStorage($config[self::DOCUMENT_PUBLIC_STORAGE] ?? '')
            ->setPublicDomain(
                $config[self::PUBLIC_DOMAIN_NAME] ?? ''
            )
        ;
    }

    public function getPublicDomain(): string
    {
        return $this->publicDomain;
    }

    public function setPublicDomain(string $publicDomain): static
    {
        $this->publicDomain = $publicDomain;

        return $this;
    }

    public function getPublicStorage(): string
    {
        return $this->publicStorage;
    }

    public function setPublicStorage(string $publicStorage): static
    {
        $this->publicStorage = $publicStorage;

        return $this;
    }
}
