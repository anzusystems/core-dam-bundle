<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\LicenceCollectionInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class LicenceCollectionSingleExtSystemValidator extends ConstraintValidator
{
    /**
     * @param LicenceCollectionSingleExtSystem $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof LicenceCollectionInterface)) {
            throw new UnexpectedTypeException($constraint, LicenceCollectionInterface::class);
        }

        $extSystem = null;
        foreach ($value->getLicences() as $licence) {
            if (null === $extSystem) {
                $extSystem = $licence->getExtSystem();

                continue;
            }

            if ($licence->getExtSystem()->isNot($extSystem)) {
                $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                    ->atPath('licences')
                    ->addViolation()
                ;

                return;
            }
        }
    }
}
