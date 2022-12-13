<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Model\Decorator\DistributionServiceAuthorization;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class DistributionPermissionFacade
{
    public function __construct(
        private readonly ModuleProvider $moduleProvider,
    ) {
    }

    public function isDistributionServiceAuthorized(string $distributionService): DistributionServiceAuthorization
    {
        try {
            $module = $this->moduleProvider->provideModule($distributionService);
        } catch (Throwable) {
            throw new NotFoundHttpException("Distribution service ({$distributionService}) not configured");
        }

        return DistributionServiceAuthorization::getInstance(
            $distributionService,
            $module->isAuthenticated($distributionService)
        );
    }
}
