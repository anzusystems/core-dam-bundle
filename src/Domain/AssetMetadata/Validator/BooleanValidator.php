<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Validator;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class BooleanValidator implements ElementValidatorInterface
{
    public function validate(
        CustomFormElement $element,
        ExecutionContextInterface $context,
        string $path,
        mixed $value,
    ): void {
        if (false === (null === $value) && false === is_bool($value)) {
            $context->buildViolation(ValidationException::ERROR_FIELD_INVALID)
                ->atPath($path)
                ->addViolation();
        }
        if ($element->getAttributes()->isRequired() && false === is_bool($value)) {
            $context->buildViolation(ValidationException::ERROR_FIELD_EMPTY)
                ->atPath($path)
                ->addViolation();
        }
    }

    public static function getDefaultKeyName(): string
    {
        return CustomFormElementType::Boolean->toString();
    }
}
