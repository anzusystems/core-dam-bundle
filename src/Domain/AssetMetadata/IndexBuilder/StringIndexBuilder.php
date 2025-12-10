<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\CustomDataIndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;

final class StringIndexBuilder implements IndexBuilderInterface
{
    public const string TITLE_KEY = 'title';
    public const string DESCRIPTION_KEY = 'description';

    public const string CUSTOM_DATA_TITLE_KEY = CustomDataIndexDefinitionFactory::METADATA_PREFIX . self::TITLE_KEY;
    public const string CUSTOM_DESCRIPTION_KEY = CustomDataIndexDefinitionFactory::METADATA_PREFIX . self::DESCRIPTION_KEY;

    public static function getDefaultKeyName(): string
    {
        return CustomFormElementType::String->toString();
    }

    public static function optimizeImageCustomData(array $searchableCustomData): array
    {
        if (isset($searchableCustomData[self::CUSTOM_DESCRIPTION_KEY])) {
            $searchableCustomData[self::CUSTOM_DATA_TITLE_KEY] = $searchableCustomData[self::CUSTOM_DESCRIPTION_KEY];
        }

        return $searchableCustomData;
    }

    public function getIndexDefinition(CustomFormElement $element): array
    {
        $fields = [
            'edgegrams' => [
                'type' => 'text',
                'analyzer' => 'edgegrams',
            ],
        ];
        if (self::TITLE_KEY === $element->getProperty() || self::DESCRIPTION_KEY === $element->getProperty()) {
            $fields['lang'] = [
                'type' => 'text',
                'analyzer' => 'lang',
            ];
        }

        return [
            'type' => 'text',
            'analyzer' => 'exact',
            'fields' => $fields,
        ];
    }
}
