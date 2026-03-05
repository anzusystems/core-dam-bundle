<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class EqualExtSystemCollectionValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (false === ($value instanceof Collection)) {
            throw new UnexpectedTypeException($constraint, Collection::class);
        }

        $root = $this->context->getRoot();
        if (false === ($root instanceof ExtSystemInterface)) {
            throw new RuntimeException(
                sprintf(
                    'Context root in validator (%s) should be instance of (%s)',
                    self::class,
                    ExtSystemInterface::class
                )
            );
        }

        foreach ($value as $item) {
            if (false === ($item instanceof ExtSystemInterface)) {
                throw new UnexpectedTypeException($constraint, ExtSystemInterface::class);
            }

            if ($root->getExtSystem()->isNot($item->getExtSystem())) {
                $this->context
                    ->buildViolation(ValidationException::ERROR_INVALID_EXT_SYSTEM)
                    ->addViolation();

                break;
            }
        }
    }
}
