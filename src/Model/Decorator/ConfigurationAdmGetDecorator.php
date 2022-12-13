<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ConfigurationAdmGetDecorator
{
    private SettingsConfiguration $settings;

    public static function getInstance(
        SettingsConfiguration $settingsConfiguration
    ): self {
        return (new self())
            ->setSettings($settingsConfiguration);
    }

    #[Serialize]
    public function getSettings(): SettingsConfigurationAdmGetDecorator
    {
        return SettingsConfigurationAdmGetDecorator::getInstance($this->settings);
    }

    public function setSettings(SettingsConfiguration $settings): self
    {
        $this->settings = $settings;

        return $this;
    }
}
