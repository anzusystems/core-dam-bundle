<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Validator;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class NumberValidator implements ElementValidatorInterface
{
    public function validate(
        CustomFormElement $element,
        ExecutionContextInterface $context,
        string $path,
        mixed $value,
    ): void {
        if (false === (null === $value) && false === is_int($value)) {
            $context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath($path)
                ->addViolation();
        }
        if ($element->getAttributes()->isRequired() && empty($value)) {
            $context->buildViolation(ValidationException::ERROR_FIELD_EMPTY)
                ->atPath($path)
                ->addViolation();
        }
        if ($element->getAttributes()->getMinValue() && $value < $element->getAttributes()->getMinValue()) {
            $context->buildViolation(ValidationException::ERROR_FIELD_RANGE_MIN)
                ->atPath($path)
                ->addViolation();
        }
        if ($element->getAttributes()->getMaxValue() && $value > $element->getAttributes()->getMaxValue()) {
            $context->buildViolation(ValidationException::ERROR_FIELD_RANGE_MAX)
                ->atPath($path)
                ->addViolation();
        }
    }

    public static function getDefaultKeyName(): string
    {
        return CustomFormElementType::Number->toString();
    }
}
