<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemAssetTypeFileVersionsConfiguration
{
    public const DEFAULT_KEY = 'default';
    public const VERSIONS_KEY = 'versions';

    public function __construct(
        private readonly string $default,
        private readonly array $versions,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        return new self(
            $config[self::DEFAULT_KEY] ?? '',
            $config[self::VERSIONS_KEY] ?? [],
        );
    }

    public function getDefault(): string
    {
        return $this->default;
    }

    public function getVersions(): array
    {
        return $this->versions;
    }
}
