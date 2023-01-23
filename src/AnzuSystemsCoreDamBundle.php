<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle;

use AnzuSystems\CoreDamBundle\DependencyInjection\AnzuSystemsCoreDamExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AnzuSystemsCoreDamBundle extends Bundle
{
    public const TAG_FILESYSTEM = 'core_dam_bundle.storage';

    public function build(ContainerBuilder $container): void
    {
        $container->registerExtension(new AnzuSystemsCoreDamExtension());
    }
}
