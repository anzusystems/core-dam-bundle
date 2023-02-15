<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Parser;

use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;

final class ArrayParser implements ElementParserInterface
{
    public static function getDefaultKeyName(): string
    {
        return CustomFormElementType::StringArray->toString();
    }

    public function parse(CustomFormElement $element, mixed $value): array
    {
        /** @psalm-suppress PossiblyNullArgument */
        $parts = explode(
            separator: ',',
            string: (string) $value,
            limit: $element->getAttributes()->getMaxCount()
        );

        return array_map(
            fn (string $part): string => StringHelper::parseString(
                $part,
                $element->getAttributes()->getMaxValue()
            ),
            $parts
        );
    }
}
