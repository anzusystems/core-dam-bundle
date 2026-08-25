<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder\StringIndexBuilder;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

final class DefaultAssetFulltextQueryBuilder implements AssetFulltextQueryBuilderInterface
{
    private const array BOOST_FIELDS = [
        StringIndexBuilder::CUSTOM_DATA_TITLE_KEY => [
            StringIndexBuilder::CUSTOM_DATA_TITLE_KEY => 5,
            StringIndexBuilder::CUSTOM_DATA_TITLE_KEY . '.edgegrams' => 1,
            StringIndexBuilder::CUSTOM_DATA_TITLE_KEY . '.lang' => 1,
        ],
        StringIndexBuilder::CUSTOM_DESCRIPTION_KEY => [
            StringIndexBuilder::CUSTOM_DESCRIPTION_KEY . '.lang' => 1,
        ],
        self::AUTHOR_NAMES_FIELD => [
            self::AUTHOR_NAMES_FIELD => 2,
            self::AUTHOR_NAMES_FIELD . '.lang' => 1,
        ],
        self::KEYWORD_NAMES_FIELD => [
            self::KEYWORD_NAMES_FIELD => 2,
            self::KEYWORD_NAMES_FIELD . '.lang' => 1,
        ],
    ];
    private const string AUTHOR_NAMES_FIELD = 'authorNames';
    private const string KEYWORD_NAMES_FIELD = 'keywordNames';

    private const string EDGEGRAMS_SUFFIX = '.edgegrams';
    private const string FUZZINESS = 'AUTO';
    private const int FUZZINESS_PREFIX_LENGTH = 2;
    private const int FUZZINESS_MAX_EXPANSIONS = 50;
    private const float FUZZY_CLAUSE_BOOST = 0.5;

    public function __construct(
        private readonly bool $searchNext = true,
    ) {
    }

    /**
     * Two clauses, because fuzziness must not reach the edge-ngram fields. Those hold every prefix
     * of every word, so an edit-distance match against them compares the query to fragments rather
     * than to words: "pica" lands one substitution away from "pira", a prefix of "Pirátska", and
     * pulls in a document nobody searched for. The exact clause keeps prefix search working, the
     * fuzzy clause covers typos and forms the dictionary cannot reach, and its lower boost keeps
     * approximate hits below the real ones.
     *
     * @return array{bool: array{should: list<array<string, mixed>>, minimum_should_match: int}}
     */
    public function build(string $text, array $customDataFields, ExtSystem $extSystem): array
    {
        $fields = $this->boostSearchFields([
            ...$customDataFields,
            self::KEYWORD_NAMES_FIELD,
            self::AUTHOR_NAMES_FIELD,
        ]);

        return [
            'bool' => [
                'should' => [
                    ['multi_match' => self::multiMatch($text, $fields)],
                    ['multi_match' => self::multiMatch($text, self::withoutEdgegrams($fields)) + [
                        'fuzziness' => self::FUZZINESS,
                        'prefix_length' => self::FUZZINESS_PREFIX_LENGTH,
                        'max_expansions' => self::FUZZINESS_MAX_EXPANSIONS,
                        'boost' => self::FUZZY_CLAUSE_BOOST,
                    ]],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    /**
     * @param list<string> $fields
     *
     * @return array{query: string, fields: list<string>, type: string, tie_breaker: float, lenient: bool}
     */
    private static function multiMatch(string $text, array $fields): array
    {
        return [
            'query' => $text,
            'fields' => $fields,
            'type' => 'most_fields',
            'tie_breaker' => 0.3,
            'lenient' => true,
        ];
    }

    /**
     * @param list<string> $fields
     *
     * @return list<string>
     */
    private static function withoutEdgegrams(array $fields): array
    {
        return array_values(array_filter(
            $fields,
            static fn (string $field): bool => false === str_contains($field, self::EDGEGRAMS_SUFFIX),
        ));
    }

    /**
     * @param list<string> $customDataFields
     *
     * @return list<string>
     */
    private function boostSearchFields(array $customDataFields): array
    {
        if (false === $this->searchNext) {
            foreach ($customDataFields as $key => $field) {
                $customDataFields[$key] = $field . '^' . ($key + 1);
            }

            return $customDataFields;
        }

        $searchFields = [];
        foreach ($customDataFields as $field) {
            if (isset(self::BOOST_FIELDS[$field])) {
                foreach (self::BOOST_FIELDS[$field] as $boostField => $boost) {
                    $searchFields[] = $boostField . '^' . $boost;
                }

                continue;
            }

            $searchFields[] = $field;
        }

        return $searchFields;
    }
}
