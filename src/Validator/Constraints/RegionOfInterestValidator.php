<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\Model\Dto\RegionOfInterest\RegionOfInterestAdmDetailDto;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class RegionOfInterestValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof RegionOfInterestAdmDetailDto)) {
            throw new InvalidArgumentException(sprintf(
                'Validator must by applied on dto object (%s)',
                self::class
            ));
        }

        $imageAttributes = $value->getImage()->getImageAttributes();

        if ($value->getPointX() > $imageAttributes->getWidth()) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_LENGTH_MAX)
                ->atPath('pointX')
                ->addViolation();
        }
        if ($value->getPointY() > $imageAttributes->getHeight()) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_LENGTH_MAX)
                ->atPath('pointY')
                ->addViolation();
        }
        if (($imageAttributes->getWidth() * $value->getPercentageWidth() + $value->getPointX()) > $imageAttributes->getWidth()) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_LENGTH_MAX)
                ->atPath('percentageWidth')
                ->addViolation();
        }
        if (($imageAttributes->getHeight() * $value->getPercentageHeight() + $value->getPointY()) > $imageAttributes->getHeight()) {
            $this->context->buildViolation(ValidationException::ERROR_FIELD_LENGTH_MAX)
                ->atPath('percentageHeight')
                ->addViolation();
        }
    }
}
