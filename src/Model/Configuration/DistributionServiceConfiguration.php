<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;

class DistributionServiceConfiguration
{
    public const string TYPE_KEY = 'type';
    public const string MODULE_KEY = 'module';
    public const string TITLE_KEY = 'title';
    public const string OPTIONS_KEY = 'options';
    public const string USE_MOCK_KEY = 'use_mock';
    public const string MOCK_OPTIONS_KEY = 'mock_options';
    public const string AUTH_REDIRECT_URL_KEY = 'auth_redirect_url';
    public const string ICON_PATH = 'icon_path';
    public const string REQUIRED_AUTH_KEY = 'required_auth';
    public const string ALLOWED_REDISTRIBUTE_STATUSES = 'redistribute_statuses';

    public function __construct(
        private readonly array $allowedRedistributeStatuses,
        private readonly string $type,
        private readonly string $module,
        private readonly string $title,
        private readonly array $options,
        private readonly bool $useMock,
        private readonly DistributionServiceMockOptionsConfiguration $mockOptions,
        private readonly bool $requiredAuth,
        private readonly ?string $authRedirectUrlKey = null,
        private string $iconPath = '',
        private string $serviceId = '',
    ) {
    }

    public static function getFromArrayConfiguration(array $config): static
    {
        return new static(
            array_filter(
                array_map(
                    fn (string $status): ?DistributionProcessStatus => DistributionProcessStatus::tryFrom($status),
                    $config[self::ALLOWED_REDISTRIBUTE_STATUSES] ?? []
                )
            ),
            $config[self::TYPE_KEY] ?? '',
            $config[self::MODULE_KEY] ?? '',
            $config[self::TITLE_KEY] ?? '',
            $config[self::OPTIONS_KEY] ?? [],
            $config[self::USE_MOCK_KEY] ?? false,
            DistributionServiceMockOptionsConfiguration::getFromArrayConfiguration(
                $config[self::MOCK_OPTIONS_KEY] ?? []
            ),
            $config[self::REQUIRED_AUTH_KEY] ?? false,
            $config[self::AUTH_REDIRECT_URL_KEY] ?? null,
            $config[self::ICON_PATH] ?? '',
        );
    }

    public function getAllowedRedistributeStatuses(): array
    {
        return $this->allowedRedistributeStatuses;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function setServiceId(string $serviceId): self
    {
        $this->serviceId = $serviceId;

        return $this;
    }

    public function isUseMock(): bool
    {
        return $this->useMock;
    }

    public function getMockOptions(): DistributionServiceMockOptionsConfiguration
    {
        return $this->mockOptions;
    }

    public function getAuthRedirectUrlKey(): ?string
    {
        return $this->authRedirectUrlKey;
    }

    public function isRequiredAuth(): bool
    {
        return $this->requiredAuth;
    }

    public function getIconPath(): string
    {
        return $this->iconPath;
    }

    public function setIconPath(string $iconPath): self
    {
        $this->iconPath = $iconPath;

        return $this;
    }
}
