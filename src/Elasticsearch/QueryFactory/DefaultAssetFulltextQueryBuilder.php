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
    ];
    private const string AUTHOR_NAMES_FIELD = 'authorNames';

    public function __construct(
        private readonly bool $searcNext = true,
    ) {
    }

    /**
     * @return array{multi_match: array{query: string, fields: list<string>, type: string, tie_breaker: float, lenient: bool}}
     */
    public function build(string $text, array $customDataFields, ExtSystem $extSystem): array
    {
        return [
            'multi_match' => [
                'query' => $text,
                'fields' => $this->boostSearchFields([...$customDataFields, self::AUTHOR_NAMES_FIELD]),
                'type' => 'most_fields',
                'tie_breaker' => 0.3,
                'lenient' => true,
            ],
        ];
    }

    /**
     * @param list<string> $customDataFields
     *
     * @return list<string>
     */
    private function boostSearchFields(array $customDataFields): array
    {
        if (false === $this->searcNext) {
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
