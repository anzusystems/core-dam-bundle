<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemDocumentTypeConfiguration extends ExtSystemAssetTypeConfiguration implements AssetFileRouteConfigurableInterface
{
    public const string DOCUMENT_PUBLIC_STORAGE = 'public_storage';
    public const string PUBLIC_DOMAIN_NAME = 'public_domain_name';

    private string $publicStorage;
    private string $publicDomainName;

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
        return $this->publicDomainName;
    }

    public function setPublicDomain(string $publicDomainName): static
    {
        $this->publicDomainName = $publicDomainName;

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
