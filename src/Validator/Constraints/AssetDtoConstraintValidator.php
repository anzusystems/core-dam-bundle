<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmUpdateDto;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetDtoConstraintValidator extends ConstraintValidator
{
    /**
     * @param AssetDtoConstraint $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (false === ($value instanceof AssetAdmUpdateDto)) {
            throw new UnexpectedTypeException($constraint, AssetAdmUpdateDto::class);
        }

        if (null === $value->getDistributionCategory()) {
            return;
        }

        if (
            $value->getAsset()->getAssetType() === $value->getDistributionCategory()->getType() &&
            $value->getAsset()->getExtSystem() === $value->getDistributionCategory()->getExtSystem()
        ) {
            return;
        }

        $this->context
            ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
            ->atPath('distributionCategory')
            ->addViolation();
    }
}
