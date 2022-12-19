<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final readonly class NotificationsConfiguration
{
    public const ENABLED = 'enabled';
    public const TOPIC = 'topic';
    public const GPS_CONFIG = 'gps_config';

    public function __construct(
        private bool $enabled,
        private string $topic,
        private array $gpsConfig,
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
