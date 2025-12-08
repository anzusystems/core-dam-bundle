<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class CacheConfiguration
{
    public const string DOMAIN = 'domain';
    public const string MAX_AGE = 'max_age';
    public const string CACHE_TTL = 'cache_ttl';
    public const string PUBLIC = 'public';
    public const string MUST_REVALIDATE = 'must_revalidate';

    private string $domain;
    private int $maxAge;
    private int $cacheTtl;
    private bool $public;
    private bool $musRevalidate = false;

    public static function getFromArrayConfiguration(array $config): self
    {
        return (new self())
            ->setDomain($config[self::DOMAIN] ?? '')
            ->setMaxAge($config[self::MAX_AGE] ?? 0)
            ->setCacheTtl($config[self::CACHE_TTL] ?? 0)
            ->setPublic($config[self::PUBLIC] ?? false)
            ->setMusRevalidate($config[self::MUST_REVALIDATE] ?? false)
        ;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    public function setMaxAge(int $maxAge): self
    {
        $this->maxAge = $maxAge;

        return $this;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function setCacheTtl(int $cacheTtl): self
    {
        $this->cacheTtl = $cacheTtl;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }

    public function isMusRevalidate(): bool
    {
        return $this->musRevalidate;
    }

    public function setMusRevalidate(bool $musRevalidate): self
    {
        $this->musRevalidate = $musRevalidate;

        return $this;
    }
}
