<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Parser;

use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;

final class StringParser implements ElementParserInterface
{
    public static function getDefaultKeyName(): string
    {
        return CustomFormElementType::String->toString();
    }

    public function parse(CustomFormElement $element, mixed $value): string
    {
        return StringHelper::parseString((string) $value, $element->getAttributes()->getMaxValue());
    }
}
