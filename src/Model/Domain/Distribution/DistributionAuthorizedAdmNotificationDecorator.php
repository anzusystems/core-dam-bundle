<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Distribution;

use AnzuSystems\CoreDamBundle\Event\DistributionAuthorized;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DistributionAuthorizedAdmNotificationDecorator
{
    #[Serialize]
    private string $distributionService;

    #[Serialize]
    private bool $success;

    public static function getInstance(DistributionAuthorized $distribution): self
    {
        return (new self())
            ->setDistributionService($distribution->getDistributionService())
            ->setSuccess($distribution->isSuccess())
        ;
    }

    public function getDistributionService(): string
    {
        return $this->distributionService;
    }

    public function setDistributionService(string $distributionService): self
    {
        $this->distributionService = $distributionService;

        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;

        return $this;
    }
}
