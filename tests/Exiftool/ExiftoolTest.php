<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Exiftool;

use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;

final class ExiftoolTest extends CoreDamKernelTestCase
{
    private const string FIXTURE = __DIR__ . '/../data/Files/exifTags.jpg';

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
}
