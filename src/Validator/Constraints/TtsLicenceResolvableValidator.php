<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
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

        $resolved = $value->getAssetLicence() ?? $value->getExtSystem()->getTtsDefaultAssetLicence();
        if (null === $resolved) {
            $this->context
                ->buildViolation(ValidationException::ERROR_FIELD_EMPTY)
                ->atPath('assetLicence')
                ->addViolation();
        }
    }
}
