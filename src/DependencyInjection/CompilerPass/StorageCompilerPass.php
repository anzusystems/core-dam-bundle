<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CoreDamBundle\AnzuSystemsCoreDamBundle;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

final class StorageCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container
            ->getDefinition(FileSystemProvider::class)
            ->setArgument(
                '$fileSystems',
                tagged_iterator(AnzuSystemsCoreDamBundle::TAG_FILESYSTEM, 'key')
            )
        ;
    }
}
