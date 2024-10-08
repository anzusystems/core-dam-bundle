<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class DistributionManagerProvider
{
    private ServiceLocator $manager;

    public function __construct(
        #[AutowireLocator(DistributionManagerInterface::class, indexAttribute: 'key')]
        ServiceLocator $manager,
    ) {
        $this->manager = $manager;
    }

    public function get(string $providerName): DistributionManagerInterface
    {
        return $this->manager->get($providerName);
    }
}
