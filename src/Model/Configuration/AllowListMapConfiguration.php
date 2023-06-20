<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class AllowListMapConfiguration
{
    public const ALLOW_LIST_KEY = 'crop_allow_list';
    public const DOMAIN_KEY = 'domain';
    public const EXT_SYSTEM_SLUGS = 'ext_system_slugs';

    private string $allowList;
    private string $domainKey;
    private array $extSystemSlugs;

    public static function getFromArrayConfiguration(array $config): self
    {
        return (new self())
            ->setAllowList($config[self::ALLOW_LIST_KEY] ?? '')
            ->setDomainKey($config[self::DOMAIN_KEY] ?? '')
            ->setExtSystemSlugs($config[self::EXT_SYSTEM_SLUGS] ?? [])
        ;
    }

    public function getAllowList(): string
    {
        return $this->allowList;
    }

    public function setAllowList(string $allowList): self
    {
        $this->allowList = $allowList;

        return $this;
    }

    public function getDomainKey(): string
    {
        return $this->domainKey;
    }

    public function setDomainKey(string $domainKey): self
    {
        $this->domainKey = $domainKey;

        return $this;
    }

    public function getExtSystemSlugs(): array
    {
        return $this->extSystemSlugs;
    }

    public function setExtSystemSlugs(array $extSystemSlugs): self
    {
        $this->extSystemSlugs = $extSystemSlugs;

        return $this;
    }
}
