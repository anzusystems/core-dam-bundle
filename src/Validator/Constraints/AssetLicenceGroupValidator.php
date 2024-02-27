<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup as EntityAssetLicenceGroup;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetLicenceGroupValidator extends ConstraintValidator
{
    /**
     * @param AssetLicenceGroup $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (false === ($value instanceof EntityAssetLicenceGroup)) {
            throw new UnexpectedTypeException($constraint, EntityAssetLicenceGroup::class);
        }

        foreach ($value->getLicences() as $licence) {
            if ($licence->getExtSystem()->getId() !== $value->getExtSystem()->getId()) {
                $this->context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                    ->atPath('licences')
                    ->addViolation();

                break;
            }
        }
    }
}
