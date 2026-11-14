<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Enum\ExtSystemSourcesRequired;

final class ExtSystemAssetTypeSourcesConfiguration
{
    public const string ENABLED_KEY = 'enabled';
    public const string REQUIRED_KEY = 'required';

    public function __construct(
        private readonly bool $enabled,
        private readonly ExtSystemSourcesRequired $required,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        $required = $config[self::REQUIRED_KEY] ?? false;

        return new self(
            $config[self::ENABLED_KEY] ?? false,
            ExtSystemSourcesRequired::tryFrom((string) $required) ?? ExtSystemSourcesRequired::Default,
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isRequiredInAdm(): bool
    {
        return $this->enabled && $this->required->is(ExtSystemSourcesRequired::Adm);
    }
}
