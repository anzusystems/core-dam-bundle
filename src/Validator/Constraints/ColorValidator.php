<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ColorValidator extends ConstraintValidator
{
    private const string PATTERN = '/^#[a-f0-9]{6}$/i';

    /**
     * @param Color $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($constraint->multiple && is_array($value)) {
            foreach ($value as $color) {
                $this->validateColor((string) $color, $constraint);
            }

            return;
        }

        if (false === $constraint->multiple && is_string($value)) {
            $this->validateColor($value, $constraint);
        }
    }

    private function validateColor(string $color, Color $constraint): void
    {
        if (0 === preg_match(self::PATTERN, $color)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
