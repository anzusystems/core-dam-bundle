<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\AssetSlot\AssetSlotMinimalAdmDto;
use AnzuSystems\CoreDamBundle\Traits\ExtSystemConfigurationProviderAwareTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AssetSlotNameValidator extends ConstraintValidator
{
    use ExtSystemConfigurationProviderAwareTrait;

    /**
     * @param AssetSlotMinimalAdmDto $value
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof AssetSlotMinimalAdmDto)) {
            throw new UnexpectedTypeException($constraint, AssetSlotMinimalAdmDto::class);
        }

        $this->validateSlot($value->getAssetFile(), $value->getSlotName());
    }

    private function validateSlot(AssetFile $assetFile, string $slot): void
    {
        $assetTypeConfiguration = $this->extSystemConfigurationProvider->getAssetConfiguration(
            $assetFile->getExtSystem()->getSlug(),
            $assetFile->getAssetType()
        );

        if (in_array($slot, $assetTypeConfiguration->getSlots()->getSlots(), true)) {
            return;
        }

        $this->context
            ->buildViolation(ValidationException::ERROR_FIELD_INVALID)
            ->atPath('slotName')
            ->addViolation();
    }
}
