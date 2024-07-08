<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemAssetTypeDistributionRequirementCategorySelectConfiguration
{
    public const string ENABLED_KEY = 'enabled';
    public const string REQUIRED_KEY = 'required';

    public function __construct(
        private readonly bool $enabled,
        private readonly bool $required,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        return new self(
            $config[self::ENABLED_KEY] ?? false,
            $config[self::REQUIRED_KEY] ?? false,
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }
}
