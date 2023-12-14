<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Cache\AssetFileRouteGenerator;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

final class AssetFileRouteUrlHandler extends AbstractHandler
{
    public function __construct(
        private readonly AssetFileRouteGenerator $assetFileRouteGenerator,
    ) {
    }

    public function serialize(mixed $value, Metadata $metadata): string
    {
        if (null === $value) {
            return '';
        }

        if ($value instanceof AssetFileRoute) {
            return $this->assetFileRouteGenerator->getFullUrl($value);
        }

        return '';
    }

    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }
}
