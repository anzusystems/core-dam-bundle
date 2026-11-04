<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

/**
 * Admin text inputs submit "" when a field is cleared; the default BasicHandler would keep the empty string
 * instead of null — use on nullable string fields where "unset" must round-trip as null.
 */
final class EmptyStringToNullHandler extends AbstractHandler
{
    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): ?string
    {
        return $value;
    }

    public function deserialize(mixed $value, Metadata $metadata): ?string
    {
        if (null === $value || (is_string($value) && App::EMPTY_STRING === trim($value))) {
            return null;
        }

        return (string) $value;
    }
}
