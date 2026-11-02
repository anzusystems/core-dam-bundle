<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Admin text inputs submit "" when a field is cleared; the default BasicHandler would cast that to 0 / ""
 * instead of null — use on nullable scalar fields where "unset" must round-trip as null.
 */
final class EmptyStringToNullHandler extends AbstractHandler
{
    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): string|int|float|null
    {
        return $value;
    }

    public function deserialize(mixed $value, Metadata $metadata): string|int|float|null
    {
        if (null === $value || (is_string($value) && App::EMPTY_STRING === trim($value))) {
            return null;
        }

        return match ($metadata->type) {
            TypeIdentifier::INT->value => (int) $value,
            TypeIdentifier::FLOAT->value => (float) $value,
            default => (string) $value,
        };
    }
}
