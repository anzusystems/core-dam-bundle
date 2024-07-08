<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Model\Decorator\DistributionAdmGetDecorator;
use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

final class DistributionHandler extends AbstractHandler
{
    use SerializerAwareTrait;

    public function __construct(
        private readonly ModuleProvider $moduleProvider,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): mixed
    {
        return $this->serializer->toArray(DistributionAdmGetDecorator::getInstance(
            $this->moduleProvider->provideModule($value)
        ));
    }

    /**
     * @throws SerializerException
     */
    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }
}
