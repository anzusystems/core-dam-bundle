<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class SettingsConfigurationAdmGetDecorator
{
    private SettingsConfiguration $configuration;

    public static function getInstance(SettingsConfiguration $configuration): self
    {
        return (new self())
            ->setConfiguration($configuration);
    }

    public function getConfiguration(): SettingsConfiguration
    {
        return $this->configuration;
    }

    public function setConfiguration(SettingsConfiguration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    #[Serialize]
    public function getDefaultExtSystemId(): int
    {
        return $this->configuration->getDefaultExtSystemId();
    }

    #[Serialize]
    public function getDefaultAssetLicenceId(): int
    {
        return $this->configuration->getDefaultAssetLicenceId();
    }

    #[Serialize]
    public function isAllowSelectExtSystem(): bool
    {
        return $this->configuration->isAllowSelectExtSystem();
    }

    #[Serialize]
    public function isAllowSelectLicenceId(): bool
    {
        return $this->configuration->isAllowSelectLicenceId();
    }

    #[Serialize]
    public function getMaxBulkItemCount(): int
    {
        return $this->configuration->getMaxBulkItemCount();
    }

    #[Serialize]
    public function getImageChunkConfig(): SettingsChunkConfigurationAdmGetDecorator
    {
        return SettingsChunkConfigurationAdmGetDecorator::getInstance($this->configuration->getImageChunkConfig());
    }

    #[Serialize]
    public function isAclCheckEnabled(): bool
    {
        return $this->configuration->isAclCheckEnabled();
    }

    #[Serialize]
    public function getAdminAllowListName(): string
    {
        return $this->configuration->getAdminAllowListName();
    }
}
