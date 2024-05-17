<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemAssetTypeSlotsConfiguration
{
    public const string DEFAULT_KEY = 'default';
    public const string SLOTS_KEY = 'slots';

    public function __construct(
        private readonly string $default,
        private readonly array $slots,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        return new self(
            $config[self::DEFAULT_KEY] ?? '',
            $config[self::SLOTS_KEY] ?? [],
        );
    }

    public function getDefault(): string
    {
        return $this->default;
    }

    public function getSlots(): array
    {
        return $this->slots;
    }
}
