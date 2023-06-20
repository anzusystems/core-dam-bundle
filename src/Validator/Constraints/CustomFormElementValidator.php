<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class CustomFormElementValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof CustomFormElement)) {
            throw new InvalidArgumentException(sprintf(
                'Validator must by applied on class (%s)',
                CustomFormElement::class
            ));
        }

        if ($value->getAttributes()->isSearchable() && false === $value->getAttributes()->getType()->allowedSearch()) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath('attributes.searchable')
                ->addViolation();
        }
    }
}
