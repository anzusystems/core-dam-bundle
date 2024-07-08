<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

use AnzuSystems\CoreDamBundle\Distribution\Modules\MockDistributionModule;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Traversable;

final class ModuleProvider
{
    /**
     * @var Traversable<string, DistributionModuleInterface>
     */
    private Traversable $distributionModules;

    /**
     * @var array<string, DistributionAdapterInterface|null>
     */
    private array $adapterCache = [];

    public function __construct(
        #[AutowireIterator(tag: DistributionModuleInterface::class, indexAttribute: 'key')]
        Traversable $distributionModules,
        private readonly DistributionConfigurationProvider $configurationProvider,
    ) {
        $this->distributionModules = $distributionModules;
    }

    public function provideCustomDistributionModule(string $distributionService): ?CustomDistributionInterface
    {
        $module = $this->provideModule($distributionService);
        if ($module instanceof CustomDistributionInterface) {
            return $module;
        }

        return null;
    }

    public function providePreviewProvidableModule(string $distributionService): ?PreviewProvidableModuleInterface
    {
        $module = $this->provideModule($distributionService);
        if ($module instanceof PreviewProvidableModuleInterface) {
            return $module;
        }

        return null;
    }

    public function provideAdapter(string $distributionService): ?DistributionAdapterInterface
    {
        if (false === isset($this->adapterCache[$distributionService])) {
            $this->adapterCache[$distributionService] =
                $this->provideCustomDistributionModule($distributionService)?->provideAdapter();
        }

        return $this->adapterCache[$distributionService];
    }

    public function provideModule(string $distributionService, bool $allowToProvideMock = false): DistributionModuleInterface
    {
        $serviceConfig = $this->configurationProvider->getDistributionService($distributionService);
        $modules = iterator_to_array($this->distributionModules);

        if ($allowToProvideMock && $serviceConfig->isUseMock() && isset($modules[MockDistributionModule::class])) {
            return $modules[MockDistributionModule::class];
        }

        if (isset($modules[$serviceConfig->getModule()])) {
            return $modules[$serviceConfig->getModule()];
        }

        throw new RuntimeException(
            sprintf(
                'Module not found for distribution (%s)',
                $serviceConfig->getModule(),
            ),
        );
    }
}
