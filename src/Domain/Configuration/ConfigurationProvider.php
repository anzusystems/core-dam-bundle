<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\DisplayTitleConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\SettingsConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\CropAllowItem;

final class ConfigurationProvider
{
    public const IMAGE_SETTINGS_OPTIMAL_RESIZES = 'optimal_resizes';

    private ?SettingsConfiguration $settingsConfiguration = null;
    private ?DisplayTitleConfiguration $displayTitleConfiguration = null;

    public function __construct(
        private readonly array $imageSettings,
        private readonly array $domains,
        private readonly array $settings,
        private readonly array $displayTitle,
        private readonly AllowListConfiguration $allowListConfiguration,
        private readonly array $colorSet,
    ) {
    }

    public function getColorSet(): array
    {
        return $this->colorSet;
    }

    public function getSettings(): SettingsConfiguration
    {
        if (null === $this->settingsConfiguration) {
            $this->settingsConfiguration = SettingsConfiguration::getFromArrayConfiguration($this->settings);
        }

        return $this->settingsConfiguration;
    }

    public function setDisplayTitleConfiguration(?DisplayTitleConfiguration $displayTitleConfiguration): void
    {
        $this->displayTitleConfiguration = $displayTitleConfiguration;
    }

    public function getDisplayTitle(): DisplayTitleConfiguration
    {
        if (null === $this->displayTitleConfiguration) {
            $this->displayTitleConfiguration = DisplayTitleConfiguration::getFromArrayConfiguration($this->displayTitle);
        }

        return $this->displayTitleConfiguration;
    }

    /**
     * @return array<int, int>
     */
    public function getImageOptimalResizes(): array
    {
        if (
            isset($this->imageSettings[self::IMAGE_SETTINGS_OPTIMAL_RESIZES]) &&
            is_array($this->imageSettings[self::IMAGE_SETTINGS_OPTIMAL_RESIZES])
        ) {
            return $this->imageSettings[self::IMAGE_SETTINGS_OPTIMAL_RESIZES];
        }

        return [];
    }

    public function getAdminAllowListName(): string
    {
        return $this->getSettings()->getAdminAllowListName();
    }

    public function getAdminDomain(): string
    {
        return $this->domains[$this->getAdminAllowListName()]['domain'] ?? '';
    }

    /**
     * @return array<string, CropAllowItem>
     */
    public function getImageAdminSizeList(string $type): array
    {
        return $this->allowListConfiguration->getTaggedList(
            $this->getAdminAllowListName(),
            $type
        );
    }
}
