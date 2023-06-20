<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\Asset as EntityAsset;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetPropertiesValidator extends ConstraintValidator
{
    /**
     * @param AssetProperties $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (false === ($value instanceof EntityAsset)) {
            throw new UnexpectedTypeException($constraint, EntityAsset::class);
        }

        if ($constraint->assetType) {
            $this->validateType($value, $constraint->assetType);
        }
    }

    private function validateType(EntityAsset $asset, AssetType $assetType): void
    {
        if ($asset->getAssetType() === $assetType) {
            return;
        }

        $this->context
            ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
            ->addViolation();
    }
}
