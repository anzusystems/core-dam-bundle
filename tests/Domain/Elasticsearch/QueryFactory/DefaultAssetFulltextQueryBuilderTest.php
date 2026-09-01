<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory\DefaultAssetFulltextQueryBuilder;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use PHPUnit\Framework\TestCase;

final class DefaultAssetFulltextQueryBuilderTest extends TestCase
{
    public function testBuildsCurrentFulltextQuery(): void
    {
        $query = (new DefaultAssetFulltextQueryBuilder())->build(
            'search text',
            ['custom_data_title', 'custom_data_headline'],
            (new ExtSystem())->setSlug('cms'),
        );

        self::assertSame(
            [
                'bool' => [
                    'should' => [
                        [
                            'multi_match' => [
                                'query' => 'search text',
                                'fields' => [
                                    'custom_data_title^5',
                                    'custom_data_title.edgegrams^1',
                                    'custom_data_title.lang^1',
                                    'custom_data_headline',
                                    'keywordNames^2',
                                    'keywordNames.lang^1',
                                    'authorNames^2',
                                    'authorNames.lang^1',
                                ],
                                'type' => 'most_fields',
                                'tie_breaker' => 0.3,
                                'lenient' => true,
                            ],
                        ],
                        [
                            'multi_match' => [
                                'query' => 'search text',
                                // No edge-ngram field: a fuzzy match against word prefixes reaches
                                // unrelated documents ("pica" is one edit from the "pira" prefix).
                                'fields' => [
                                    'custom_data_title^5',
                                    'custom_data_title.lang^1',
                                    'custom_data_headline',
                                    'keywordNames^2',
                                    'keywordNames.lang^1',
                                    'authorNames^2',
                                    'authorNames.lang^1',
                                ],
                                'type' => 'most_fields',
                                'tie_breaker' => 0.3,
                                'lenient' => true,
                                'fuzziness' => 'AUTO',
                                'prefix_length' => 2,
                                'max_expansions' => 50,
                                'boost' => 0.5,
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
            $query,
        );
    }

    public function testPreservesLegacyPositionalBoosts(): void
    {
        $query = (new DefaultAssetFulltextQueryBuilder(false))->build(
            'search text',
            ['custom_data_title', 'custom_data_headline'],
            (new ExtSystem())->setSlug('cms'),
        );

        self::assertSame(
            [
                'custom_data_title^1',
                'custom_data_headline^2',
                'keywordNames^3',
                'authorNames^4',
            ],
            $query['bool']['should'][0]['multi_match']['fields'],
        );
    }
}
