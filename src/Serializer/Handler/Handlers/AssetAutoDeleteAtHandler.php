<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceAutoDeleteResolver;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Handler\Handlers\DateTimeHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

final class AssetAutoDeleteAtHandler extends AbstractHandler
{
    public function __construct(
        private readonly AssetLicenceAutoDeleteResolver $autoDeleteResolver,
        private readonly DateTimeHandler $dateTimeHandler,
    ) {
    }

    /**
     * @param Asset $value
     */
    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): ?string
    {
        $deleteAt = $this->autoDeleteResolver->getDeleteAt($value);
        if (null === $deleteAt) {
            return null;
        }

        return $this->dateTimeHandler->serialize($deleteAt, $metadata, $context);
    }

    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    public function describe(string $property, Metadata $metadata): array
    {
        return [
            ...$this->dateTimeHandler->describe($property, $metadata),
            'nullable' => true,
        ];
    }
}
