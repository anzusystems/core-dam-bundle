<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexSettings;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Model\Enum\Language;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class IndexDefinitionFactory
{
    /**
     * @var iterable<IndexDefinitionExtensionInterface>
     */
    private readonly iterable $indexDefinitionExtensions;

    /**
     * @param iterable<IndexDefinitionExtensionInterface> $indexDefinitionExtensions
     */
    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly CustomDataIndexDefinitionFactory $customDataIndexDefinitionFactory,
        private readonly IndexSettings $indexSettings,
        #[AutowireIterator(tag: IndexDefinitionExtensionInterface::class)]
        iterable $indexDefinitionExtensions = [],
    ) {
        $this->indexDefinitionExtensions = $indexDefinitionExtensions;
    }

    public function buildIndexDefinitions(array $indexMappings): array
    {
        $language = Language::Slovak;
        $defs = [];
        foreach ($indexMappings as $resourceName => $mappings) {
            foreach ($this->extSystemConfigurationProvider->getExtSystemSlugs() as $slug) {
                $customDataDefinitions = Asset::getIndexName() === $resourceName
                    ? $this->customDataIndexDefinitionFactory->getCustomDataDefinitions($slug)
                    : [];
                $fullIndexName = $this->indexSettings->getFullIndexNameBySlug($resourceName, $slug);

                $definition = [
                    'settings' => [
                        'analysis' => [
                            'char_filter' => $this->getCharFilters(),
                            'filter' => $this->getFilters($language),
                            'analyzer' => $this->getAnalyzers($language),
                        ],
                    ],
                    'mappings' => [
                        'properties' => array_merge(
                            $mappings,
                            $customDataDefinitions
                        ),
                    ],
                ];

                foreach ($this->indexDefinitionExtensions as $indexDefinitionExtension) {
                    if ($indexDefinitionExtension->supports($resourceName, $slug)) {
                        $definition = $indexDefinitionExtension->extend($resourceName, $slug, $definition);
                    }
                }

                $defs[$fullIndexName] = $definition;
            }
        }

        return $defs;
    }

    private function getFilters(Language $language): array
    {
        if ($this->indexSettings->hasNotElasticLanguageDictionary($language)) {
            return $this->getDefaultFilters();
        }

        return array_merge([
            'lang_hunspell' => [
                'type' => 'hunspell',
                'locale' => $language->getLocale(),
                'dedup' => true,
            ],
            'lang_stop' => [
                'type' => 'stop',
                'stopwords_path' => 'stop-words/stopwords_' . $language->toString() . '.txt',
                'ignore_case' => true,
            ],
            'lang_syn' => [
                'type' => 'synonym',
                'synonyms_path' => 'synonyms/synonyms_' . $language->toString() . '.txt',
            ],
        ], $this->getDefaultFilters());
    }

    private function getCharFilters(): array
    {
        return [
            'underscore_to_space' => [
                'type' => 'mapping',
                'mappings' => ['_ => \\u0020'],
            ],
        ];
    }

    private function getDefaultFilters(): array
    {
        return [
            'edgegrams' => [
                'type' => 'edge_ngram',
                'min_gram' => 3,
                'max_gram' => 8,
            ],
            'unique_on_pos' => [
                'type' => 'unique',
                'only_on_same_position' => true,
            ],
        ];
    }

    private function getAnalyzers(Language $language): array
    {
        $langFilters = [
            'lowercase',
            'asciifolding',
            'unique_on_pos',
        ];
        $exactStopFilters = [
            'lowercase',
            'asciifolding',
            'unique_on_pos',
        ];
        if ($this->indexSettings->hasElasticLanguageDictionary($language)) {
            $langFilters = array_merge($langFilters, [
                'lang_syn',
                'lang_stop',
                'lang_hunspell',
            ]);
            $exactStopFilters = array_merge($exactStopFilters, [
                'lang_stop',
            ]);
        }

        return array_merge([
            'lang' => [
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => $langFilters,
                'char_filter' => ['html_strip'],
            ],
            'exact_stop' => [
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => $exactStopFilters,
                'char_filter' => ['html_strip'],
            ],
        ], $this->getDefaultAnalyzers());
    }

    private function getDefaultAnalyzers(): array
    {
        return [
            'exact' => [
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => ['lowercase', 'asciifolding', 'unique_on_pos'],
                'char_filter' => ['html_strip'],
            ],
            'edgegrams' => [
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => ['lowercase', 'asciifolding', 'edgegrams', 'unique_on_pos'],
                'char_filter' => ['html_strip'],
            ],
            // Intentionally no lang_stop, unlike exact_stop — stopword removal would eat person-name particles.
            'author_exact_stop' => [
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => ['lowercase', 'asciifolding', 'unique_on_pos'],
                'char_filter' => ['html_strip', 'underscore_to_space'],
            ],
            'author_edgegrams' => [
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => ['lowercase', 'asciifolding', 'edgegrams', 'unique_on_pos'],
                'char_filter' => ['html_strip', 'underscore_to_space'],
            ],
        ];
    }
}
