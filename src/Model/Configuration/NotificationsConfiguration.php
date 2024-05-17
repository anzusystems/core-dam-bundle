<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class NotificationsConfiguration
{
    public const string ENABLED = 'enabled';
    public const string TOPIC = 'topic';
    public const string GPS_CONFIG = 'gps_config';

    public function __construct(
        private readonly bool $enabled,
        private readonly string $topic,
        private readonly array $gpsConfig,
    ) {
    }

    public static function getFromArrayConfiguration(
        array $notificationsConfig
    ): self {
        return new self(
            $notificationsConfig[self::ENABLED] ?? true,
            $notificationsConfig[self::TOPIC] ?? '',
            $notificationsConfig[self::GPS_CONFIG] ?? [],
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isDisabled(): bool
    {
        return false === $this->isEnabled();
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getGpsConfig(): array
    {
        return $this->gpsConfig;
    }
}
