<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class TtsLicenceResolvableValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (false === ($value instanceof TtsSynthesizeRequestDto)) {
            throw new UnexpectedTypeException($value, TtsSynthesizeRequestDto::class);
        }

        $extSystem = $value->getExtSystemOrNull();
        if (null === $extSystem) {
            // A missing ext system is reported by its own NotEmptyId — the licence can't be checked without it.
            return;
        }

        $assetLicence = $value->getAssetLicence();
        if (null === $assetLicence) {
            // No explicit licence → must fall back to the ext system's default.
            if (null === $extSystem->getTtsDefaultAssetLicence()) {
                $this->context->buildViolation(ValidationException::ERROR_FIELD_EMPTY)->atPath('assetLicence')->addViolation();
            }

            return;
        }

        // An explicit licence must belong to the ext system.
        if ($assetLicence->getExtSystem()->isNot($extSystem)) {
            $this->context->buildViolation(ValidationException::ERROR_INVALID_EXT_SYSTEM)->atPath('assetLicence')->addViolation();
        }
    }
}
