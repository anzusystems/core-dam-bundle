<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder;

use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;

final class StringIndexBuilder implements IndexBuilderInterface
{
    public static function getDefaultKeyName(): string
    {
        return CustomFormElementType::String->toString();
    }

    public function getIndexDefinition(CustomFormElement $element): array
    {
        return [
            'type' => 'text',
            'analyzer' => 'exact',
            'fields' => [
                'edgegrams' => [
                    'type' => 'text',
                    'analyzer' => 'edgegrams',
                ],
            ],
        ];
    }
}
