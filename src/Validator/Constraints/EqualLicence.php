<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute]
final class EqualLicence extends Constraint
{
    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
