<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Validator;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class StringValidator implements ElementValidatorInterface
{
    public function validate(
        CustomFormElement $element,
        ExecutionContextInterface $context,
        string $path,
        mixed $value,
    ): void {
        $strLength = mb_strlen((string) $value);

        if (false === (null === $value) && false === is_string($value)) {
            $context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath($path)
                ->addViolation();
        }
        if ($element->getAttributes()->isRequired() && empty($value)) {
            $context->buildViolation(ValidationException::ERROR_FIELD_EMPTY)
                ->atPath($path)
                ->addViolation();
        }
        if ($element->getAttributes()->getMinValue() && $strLength < $element->getAttributes()->getMinValue()) {
            $context->buildViolation(ValidationException::ERROR_FIELD_LENGTH_MIN)
                ->atPath($path)
                ->addViolation();
        }
        if ($element->getAttributes()->getMaxValue() && $strLength > $element->getAttributes()->getMaxValue()) {
            $context->buildViolation(ValidationException::ERROR_FIELD_LENGTH_MAX)
                ->atPath($path)
                ->addViolation();
        }
    }

    public static function getDefaultKeyName(): string
    {
        return CustomFormElementType::String->toString();
    }
}
