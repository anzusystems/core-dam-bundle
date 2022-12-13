<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Parser;

use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;

final class NumberParser implements ElementParserInterface
{
    public static function getDefaultKeyName(): string
    {
        return CustomFormElementType::Number->toString();
    }

    public function parse(CustomFormElement $element, mixed $value): int
    {
        return (int) $value;
    }
}
