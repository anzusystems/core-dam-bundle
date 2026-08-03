<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Exiftool;

use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;

final class ExiftoolTest extends CoreDamKernelTestCase
{
    private const string FIXTURE = __DIR__ . '/../data/Files/exifTags.jpg';
    private const string IPTC_CP1250_UNDECLARED_FIXTURE = __DIR__ . '/../data/Files/exifTagsIptcCp1250Undeclared.jpg';

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
     * Fixture IPTC bytes are cp1250-encoded with no IPTC:CodedCharacterSet tag (the common case for
     * archival photos). Without an assumed IPTC charset, exiftool falls back to latin-1 and mangles
     * diacritics (e.g. "Žákovič" -> "Žákoviè").
     */
    public function testCp1250IptcWithoutDeclaredCharsetIsDecodedCorrectly(): void
    {
        $tags = $this->exiftool->getTags(self::IPTC_CP1250_UNDECLARED_FIXTURE);

        self::assertSame('Peter Žákovič, Ľupča', $tags['Caption-Abstract'] ?? null);
        self::assertSame('Peter Žákovič', $tags['By-line'] ?? null);
    }
}
