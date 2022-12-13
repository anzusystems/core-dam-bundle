<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Validator;

use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[AutoconfigureTag]
interface ElementValidatorInterface
{
    public static function getDefaultKeyName(): string;

    public function validate(
        CustomFormElement $element,
        ExecutionContextInterface $context,
        string $path,
        mixed $value,
    ): void;
}
