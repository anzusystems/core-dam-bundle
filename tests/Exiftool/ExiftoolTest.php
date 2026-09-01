<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Exiftool;

use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ExiftoolTest extends CoreDamKernelTestCase
{
    private const string FIXTURE = __DIR__ . '/../data/Files/exifTags.jpg';
    private const string IPTC_CP1250_UNDECLARED_FIXTURE = __DIR__ . '/../data/Files/exifTagsIptcCp1250Undeclared.jpg';
    private const string IPTC_UTF8_UNDECLARED_FIXTURE = __DIR__ . '/../data/Files/exifTagsIptcUtf8Undeclared.jpg';
    private const string IPTC_UTF8_DECLARED_FIXTURE = __DIR__ . '/../data/Files/exifTagsIptcUtf8Declared.jpg';

    private Exiftool $exiftool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exiftool = $this->getService(Exiftool::class);
    }

    public function testTagNamesMatchConfiguredMetadataKeys(): void
    {
        $tags = $this->exiftool->getTags(self::FIXTURE);

        self::assertSame('Titulok', $tags['ObjectName'] ?? null);
        self::assertSame('Popis č. 1', $tags['Description'] ?? null);
    }

    public function testListTagIsFlattened(): void
    {
        $tags = $this->exiftool->getTags(self::FIXTURE);

        self::assertSame('alfa, beta', $tags['Keywords'] ?? null);
    }

    public function testValuesAreNotHtmlEscaped(): void
    {
        $tags = $this->exiftool->getTags(self::FIXTURE);

        self::assertSame("O'Brien & Co <b>x</b>", $tags['Artist'] ?? null);
    }

    public function testUnreadableFileYieldsNoTags(): void
    {
        self::assertSame([], $this->exiftool->getTags(__DIR__ . '/does-not-exist.jpg'));
    }

    /**
     * Neither fixture declares IPTC:CodedCharacterSet (the common case for archival photos), one carrying
     * raw cp1250 bytes and the other raw UTF-8 bytes. Per-value UTF-8 detection must decode both correctly
     * without knowing up front which charset a given file actually used.
     */
    #[DataProvider('undeclaredIptcCharsetFixtureProvider')]
    public function testIptcWithoutDeclaredCharsetIsDecodedCorrectly(string $fixture): void
    {
        $tags = $this->exiftool->getTags($fixture);

        self::assertSame('Peter Žákovič, Ľupča', $tags['Caption-Abstract'] ?? null);
        self::assertSame('Peter Žákovič', $tags['By-line'] ?? null);
    }

    /**
     * The fixture declares IPTC:CodedCharacterSet=UTF8, so exiftool already decoded it correctly and
     * charset recovery must keep its hands off: "£" lives in the Latin-1 range where cp1250 disagrees
     * with Latin-1 and would come back as "Ł".
     */
    public function testIptcWithDeclaredCharsetIsLeftAsRead(): void
    {
        $tags = $this->exiftool->getTags(self::IPTC_UTF8_DECLARED_FIXTURE);

        self::assertSame('£50 note', $tags['By-line'] ?? null);
        self::assertSame('Peter Žákovič, Ľupča', $tags['Caption-Abstract'] ?? null);
    }

    public static function undeclaredIptcCharsetFixtureProvider(): array
    {
        return [
            'cp1250 bytes' => [self::IPTC_CP1250_UNDECLARED_FIXTURE],
            'UTF-8 bytes' => [self::IPTC_UTF8_UNDECLARED_FIXTURE],
        ];
    }
}
