<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsChunkConfiguration;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class SettingsChunkConfigurationAdmGetDecorator
{
    private SettingsChunkConfiguration $configuration;

    public static function getInstance(SettingsChunkConfiguration $configuration): self
    {
        return (new self())
            ->setConfiguration($configuration);
    }

    public function getConfiguration(): SettingsChunkConfiguration
    {
        return $this->configuration;
    }

    public function setConfiguration(SettingsChunkConfiguration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    #[Serialize]
    public function getMinSize(): int
    {
        return $this->configuration->getMinSize();
    }

    #[Serialize]
    public function getMaxSize(): int
    {
        return $this->configuration->getMaxSize();
    }
}
