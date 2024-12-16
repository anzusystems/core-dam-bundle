<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidRegexValidator extends ConstraintValidator
{
    /**
     * @param ValidRegex $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (false === is_string($value)) {
            throw new UnexpectedTypeException($constraint, YoutubeDistribution::class);
        }

        if (false === @preg_match('~' . $value . '~', '')) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation()
            ;
        }
    }
}
