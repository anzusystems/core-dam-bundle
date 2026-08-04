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
                'multi_match' => [
                    'query' => 'search text',
                    'fields' => [
                        'custom_data_title^5',
                        'custom_data_title.edgegrams^1',
                        'custom_data_title.lang^1',
                        'custom_data_headline',
                        'authorNames^2',
                        'authorNames.lang^1',
                    ],
                    'type' => 'most_fields',
                    'tie_breaker' => 0.3,
                    'lenient' => true,
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
                'authorNames^3',
            ],
            $query['multi_match']['fields'],
        );
    }
}
