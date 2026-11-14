<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Enum\ExtSystemSourcesRequired;

final class ExtSystemAssetTypeSourcesConfiguration
{
    public const string ENABLED_KEY = 'enabled';
    public const string REQUIRED_KEY = 'required';
    public const string CREDIT_FORMAT_KEY = 'credit_format';
    public const string CREDIT_SEPARATOR_KEY = 'credit_separator';
    public const string DEFAULT_CREDIT_FORMAT = '{sources}/{authors}';
    public const string DEFAULT_CREDIT_SEPARATOR = ', ';

    public function __construct(
        private readonly bool $enabled,
        private readonly ExtSystemSourcesRequired $required,
        private readonly string $creditFormat,
        private readonly string $creditSeparator,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        $required = $config[self::REQUIRED_KEY] ?? false;

        return new self(
            $config[self::ENABLED_KEY] ?? false,
            ExtSystemSourcesRequired::tryFrom((string) $required) ?? ExtSystemSourcesRequired::Default,
            $config[self::CREDIT_FORMAT_KEY] ?? self::DEFAULT_CREDIT_FORMAT,
            $config[self::CREDIT_SEPARATOR_KEY] ?? self::DEFAULT_CREDIT_SEPARATOR,
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

    public function getCreditFormat(): string
    {
        return $this->creditFormat;
    }

    public function getCreditSeparator(): string
    {
        return $this->creditSeparator;
    }
}
