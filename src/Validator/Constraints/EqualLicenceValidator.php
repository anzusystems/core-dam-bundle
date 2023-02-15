<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class EqualLicenceValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (false === ($value instanceof AssetLicenceInterface)) {
            throw new UnexpectedTypeException($constraint, AssetLicenceInterface::class);
        }

        $licencedRoot = $this->context->getRoot();
        if (false === ($licencedRoot instanceof AssetLicenceInterface)) {
            throw new RuntimeException(
                sprintf(
                    'Context root in validator (%s) should be instance of (%s)',
                    self::class,
                    AssetLicenceInterface::class
                )
            );
        }

        if (false === ($licencedRoot->getLicence() === $value->getLicence())) {
            $this->context
                ->buildViolation(ValidationException::ERROR_INVALID_LICENCE)
                ->addViolation();
        }
    }
}
