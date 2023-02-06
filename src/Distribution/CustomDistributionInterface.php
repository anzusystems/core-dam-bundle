<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

interface CustomDistributionInterface
{
    public function provideAdapter(): DistributionAdapterInterface;
}
