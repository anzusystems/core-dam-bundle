<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute]
final class Color extends Constraint
{
    public string $message = ValidationException::ERROR_FIELD_INVALID;

    public function __construct(
        public bool $multiple = false,
        ?array $options = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options, $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
