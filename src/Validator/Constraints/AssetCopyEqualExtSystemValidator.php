<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetCopyEqualExtSystemValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (false === ($value instanceof ImageCopyDto)) {
            throw new UnexpectedTypeException($constraint, ImageCopyDto::class);
        }

        if (null === $value->getAsset()->getId() || null === $value->getTargetAssetLicence()->getId()) {
            return;
        }

        if ($value->getAsset()->getLicence()->getExtSystem()->isNot($value->getTargetAssetLicence()->getExtSystem())) {
            $this->context
                ->buildViolation(ValidationException::ERROR_INVALID_LICENCE)
                ->atPath('targetAssetLicence')
                ->addViolation();
        }
    }
}
