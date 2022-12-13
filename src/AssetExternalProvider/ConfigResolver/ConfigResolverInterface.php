<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\AssetExternalProvider\ConfigResolver;

use AnzuSystems\CoreDamBundle\Model\Configuration\AssetExternalProviderConfiguration;

interface ConfigResolverInterface
{
    public function resolve(array $config): AssetExternalProviderConfiguration;
}
