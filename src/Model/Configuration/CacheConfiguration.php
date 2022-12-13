<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class CacheConfiguration
{
    public const MAX_AGE = 'max_age';
    public const CACHE_TTL = 'cache_ttl';
    public const PUBLIC = 'public';

    private int $maxAge;
    private int $cacheTtl;
    private bool $public;

    public static function getFromArrayConfiguration(array $config): self
    {
        return (new self())
            ->setMaxAge($config[self::MAX_AGE] ?? 0)
            ->setCacheTtl($config[self::CACHE_TTL] ?? 0)
            ->setPublic($config[self::PUBLIC] ?? false);
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
}
