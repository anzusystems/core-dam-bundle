<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\CoreDamBundle\Distribution\DistributionModuleInterface;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DistributionAdmGetDecorator
{
    private string $resourceName;

    public static function getInstance(DistributionModuleInterface $distributionModule): self
    {
        return (new self())
            ->setResourceName($distributionModule::supportsDistributionResourceName());
    }

    #[Serialize]
    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    public function setResourceName(string $resourceName): self
    {
        $this->resourceName = $resourceName;

        return $this;
    }
}
