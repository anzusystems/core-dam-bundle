<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class EqualExtSystemValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (false === ($value instanceof ExtSystemInterface)) {
            throw new UnexpectedTypeException($constraint, ExtSystemInterface::class);
        }

        $licencedRoot = $this->context->getRoot();
        if (false === ($licencedRoot instanceof ExtSystemInterface)) {
            throw new RuntimeException(
                sprintf(
                    'Context root in validator (%s) should be instance of (%s)',
                    self::class,
                    ExtSystemInterface::class
                )
            );
        }

        if ($licencedRoot->getExtSystem()->isNot($value->getExtSystem())) {
            $this->context
                ->buildViolation(ValidationException::ERROR_INVALID_EXT_SYSTEM)
                ->addViolation();
        }
    }
}
