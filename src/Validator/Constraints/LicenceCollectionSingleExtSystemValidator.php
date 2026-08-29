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
        if (null === $value) {
            return;
        }

        if (false === ($value instanceof LicenceCollectionInterface)) {
            throw new UnexpectedTypeException($constraint, LicenceCollectionInterface::class);
        }

        $extSystemId = null;
        foreach ($value->getLicences() as $licence) {
            $licenceExtSystemId = (int) $licence->getExtSystem()->getId();
            if (null === $extSystemId) {
                $extSystemId = $licenceExtSystemId;

                continue;
            }

            if ($licenceExtSystemId !== $extSystemId) {
                $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                    ->atPath('licences')
                    ->addViolation()
                ;

                return;
            }
        }
    }
}
