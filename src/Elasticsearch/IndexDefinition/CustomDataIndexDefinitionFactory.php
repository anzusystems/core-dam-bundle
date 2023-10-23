<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition;

use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder\IndexBuilderInterface;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class CustomDataIndexDefinitionFactory
{
    private const METADATA_PREFIX = 'custom_data_';

    private readonly iterable $indexBuilders;

    public function __construct(
        #[TaggedIterator(tag: IndexBuilderInterface::class, indexAttribute: 'key')]
        iterable $indexBuilders,
        private readonly CustomFormProvider $customFormProvider,
    ) {
        $this->indexBuilders = $indexBuilders;
    }

    public function getCustomDataDefinitions(string $slug): array
    {
        $elements = $this->customFormProvider->provideAllSearchableElementsForExtSystem($slug);
        $definitions = [];

        foreach ($elements as $element) {
            $indexBuilder = $this->getIndexBuilder($element);

            $definitions[$this->getIndexKeyName($element)] = $indexBuilder->getIndexDefinition($element);
        }

        return $definitions;
    }

    public static function getIndexKeyName(CustomFormElement $element): string
    {
        return self::METADATA_PREFIX . $element->getProperty();
    }

    private function getIndexBuilder(CustomFormElement $customFormElement): IndexBuilderInterface
    {
        foreach ($this->indexBuilders as $key => $indexBuilder) {
            if ($customFormElement->getAttributes()->getType()->toString() === $key) {
                return $indexBuilder;
            }
        }

        throw new DomainException(sprintf(
            'Index builder for type (%s) is missing',
            $customFormElement->getAttributes()->getType()->toString()
        ));
    }
}
