<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Event;

final class DistributionAuthorized
{
    public function __construct(
        private readonly string $distributionService,
        private readonly int $targetUserId,
        private readonly bool $success = true,
    ) {
    }

    public function getDistributionService(): string
    {
        return $this->distributionService;
    }

    public function getTargetUserId(): int
    {
        return $this->targetUserId;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}
