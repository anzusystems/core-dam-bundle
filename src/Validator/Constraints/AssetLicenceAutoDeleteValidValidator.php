<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetLicenceAutoDelete;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class AssetLicenceAutoDeleteValidValidator extends ConstraintValidator
{
    /**
     * @param AssetLicenceAutoDeleteValid $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof AssetLicenceAutoDelete)) {
            throw new UnexpectedValueException($value, AssetLicenceAutoDelete::class);
        }

        if ($value->isNotActive()) {
            return;
        }

        if ($value->getOlderThanDays() >= AssetLicenceAutoDelete::MIN_OLDER_THAN_DAYS) {
            return;
        }

        $this->context
            ->buildViolation($constraint->message)
            ->atPath('olderThanDays')
            ->addViolation();
    }
}
