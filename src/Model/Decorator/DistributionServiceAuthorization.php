<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DistributionServiceAuthorization
{
    private string $distributionService = '';
    private bool $isAuthorized = false;

    public static function getInstance(
        string $distributionService,
        bool $isAuthorized
    ): self {
        return (new self())
            ->setDistributionService($distributionService)
            ->setIsAuthorized($isAuthorized)
        ;
    }

    #[Serialize]
    public function getDistributionService(): string
    {
        return $this->distributionService;
    }

    public function setDistributionService(string $distributionService): self
    {
        $this->distributionService = $distributionService;

        return $this;
    }

    #[Serialize]
    public function isAuthorized(): bool
    {
        return $this->isAuthorized;
    }

    public function setIsAuthorized(bool $isAuthorized): self
    {
        $this->isAuthorized = $isAuthorized;

        return $this;
    }
}
