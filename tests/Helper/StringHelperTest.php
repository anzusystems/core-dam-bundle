<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Helper;

use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StringHelperTest extends TestCase
{
    #[DataProvider('provideDoubleEncoded')]
    public function testRepairDoubleEncodedUtf8(string $input, string $expected): void
    {
        self::assertSame($expected, StringHelper::repairDoubleEncodedUtf8($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideDoubleEncoded(): iterable
    {
        // Verbatim bytes of an IPTC Credit stored by the pre-fix pipeline (core_dam.asset_file_metadata).
        yield 'production IPTC credit read as latin-1' => [
            (string) hex2bin('546C61C384C28D6F76C383C2A1206167656E74C383C2BA7261'),
            'Tlačová agentúra',
        ];
        yield 'double encoded german' => ["M\xC3\x83\xC2\xBCnchen", 'München'];
        yield 'already correct slovak' => ['Hádzanárky Nórska', 'Hádzanárky Nórska'];
        yield 'already correct with caron' => ["\xC5\xBDofia O'Brien", "Žofia O'Brien"];
        yield 'genuine latin-1 stays untouched' => ["M\xC3\xBCnchen Stra\xC3\x9Fe", "München Straße"];
        yield 'plain ascii' => ['Giovanni Auletta', 'Giovanni Auletta'];
        yield 'empty' => ['', ''];
    }
}
