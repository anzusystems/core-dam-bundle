<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class DistributionManagerProvider
{
    private readonly ServiceLocator $manager;

    public function __construct(
        #[TaggedLocator(DistributionManagerInterface::class, indexAttribute: 'key')] ServiceLocator $manager,
    ) {
        $this->manager = $manager;
    }

    public function get(string $providerName): DistributionManagerInterface
    {
        return $this->manager->get($providerName);
    }
}
