<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DependencyInjection;

use AnzuSystems\CoreDamBundle\Request\ParamConverter\ArrayStringParamConverter;
use AnzuSystems\CoreDamBundle\Request\ParamConverter\ChunkParamConverter;
use AnzuSystems\CoreDamBundle\Request\ParamConverter\CollectionParamConverter;
use AnzuSystems\CoreDamBundle\Request\ParamConverter\ImageCropConverter;
use AnzuSystems\SerializerBundle\Serializer;
use Exception;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class ParamConverterExtension extends Extension
{
    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->setDefinition(
            ChunkParamConverter::class,
            (new Definition(ChunkParamConverter::class))
                ->addMethodCall('setSerializer', [new Reference(Serializer::class)])
                ->addTag('request.param_converter', [
                    'priority' => false,
                    'converter' => ChunkParamConverter::class,
                ])
        );

        $container->setDefinition(
            CollectionParamConverter::class,
            (new Definition(CollectionParamConverter::class))
                ->addMethodCall('setSerializer', [new Reference(Serializer::class)])
                ->addTag('request.param_converter', [
                    'priority' => false,
                    'converter' => CollectionParamConverter::class,
                ])
        );

        $container->setDefinition(
            ImageCropConverter::class,
            (new Definition(ImageCropConverter::class))
                ->addTag('request.param_converter', [
                    'priority' => false,
                    'converter' => ImageCropConverter::class,
                ])
        );

        $container->setDefinition(
            ArrayStringParamConverter::class,
            (new Definition(ArrayStringParamConverter::class))
                ->addTag('request.param_converter', [
                    'priority' => false,
                    'converter' => ArrayStringParamConverter::class,
                ])
        );
    }
}
