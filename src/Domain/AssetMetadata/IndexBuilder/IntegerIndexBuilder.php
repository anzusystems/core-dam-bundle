<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder;

use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;

final class IntegerIndexBuilder implements IndexBuilderInterface
{
    public static function getDefaultKeyName(): string
    {
        return CustomFormElementType::Integer->toString();
    }

    public function getIndexDefinition(CustomFormElement $element): array
    {
        return [
            'type' => 'keyword',
        ];
    }
}
