<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetMetadata\IndexBuilder;

use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder\StringIndexBuilder;
use PHPUnit\Framework\TestCase;

final class StringIndexBuilderTest extends TestCase
{
    public function testImageDescriptionReplacesLegacyTitleIndexValue(): void
    {
        self::assertSame(
            [
                StringIndexBuilder::CUSTOM_DATA_TITLE_KEY => 'Current description',
                StringIndexBuilder::CUSTOM_DESCRIPTION_KEY => 'Current description',
            ],
            StringIndexBuilder::optimizeImageCustomData([
                StringIndexBuilder::CUSTOM_DATA_TITLE_KEY => 'Legacy title',
                StringIndexBuilder::CUSTOM_DESCRIPTION_KEY => 'Current description',
            ]),
        );
    }

}
