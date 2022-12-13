<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\UserAuthType;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ConfigurationPubGetDto
{
    private SettingsConfiguration $decoratedSettings;

    public static function getInstance(
        SettingsConfiguration $decoratedSettings,
    ): self {
        return (new self())
            ->setDecoratedSettings($decoratedSettings)
        ;
    }

    public function getDecoratedSettings(): SettingsConfiguration
    {
        return $this->decoratedSettings;
    }

    public function setDecoratedSettings(SettingsConfiguration $decoratedSettings): self
    {
        $this->decoratedSettings = $decoratedSettings;

        return $this;
    }

    #[Serialize]
    public function getUserAuthType(): UserAuthType
    {
        return $this->decoratedSettings->getUserAuthType();
    }
}
