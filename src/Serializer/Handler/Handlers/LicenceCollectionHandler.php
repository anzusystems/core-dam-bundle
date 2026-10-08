<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\BatchItem;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class LicenceCollectionHandler extends AbstractHandler
{
    use SerializerAwareTrait;

    public function __construct(
        private readonly EntityIdHandler $entityIdHandler,
    ) {
    }

    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): string
    {
        throw new SerializerException('Serialization not supported');
    }

    public function deserialize(mixed $value, Metadata $metadata): Collection
    {
        if (empty($value)) {
            return new ArrayCollection([]);
        }

        if (is_string($value)) {
            $ids = array_map(
                fn (string $item): int => (int) $item,
                explode(',', $value)
            );

            if (count($ids) > AssetLicence::COLLECTION_MAX) {
                throw (new ValidationException())->addFormattedError('licences', ValidationException::ERROR_FIELD_RANGE_MAX);
            }

            // Nested handlers run outside the deserializer's batch, so the ids must be warmed up here -
            // EntityIdHandler silently drops every id its identity map does not know.
            $this->entityIdHandler->prepareDeserializeBatch(new BatchItem($ids, $metadata));

            return $this->entityIdHandler->deserialize($ids, $metadata);
        }

        throw new SerializerException('Unsupported value for ' . self::class . '::' . __FUNCTION__);
    }
}
