<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\FileSystem;

use AnzuSystems\CoreDamBundle\FileSystem\MimeGuesser;
use PHPUnit\Framework\TestCase;

final class MimeGuesserTest extends TestCase
{
    private const string DATA_PATH = __DIR__ . '/../data/Files/';

    public function testImageDataChecksumIgnoresTheMetadata(): void
    {
        $original = self::DATA_PATH . 'exifTags.jpg';
        $rewrittenMetadata = self::DATA_PATH . 'exifTagsIptcUtf8Declared.jpg';

        self::assertNotSame(MimeGuesser::checksumFromPath($original), MimeGuesser::checksumFromPath($rewrittenMetadata));
        self::assertNotNull(MimeGuesser::imageDataChecksumFromPath($original));
        self::assertSame(MimeGuesser::imageDataChecksumFromPath($original), MimeGuesser::imageDataChecksumFromPath($rewrittenMetadata));
    }

    public function testImageDataChecksumIsNullForAFileThatIsNotAJpeg(): void
    {
        self::assertNull(MimeGuesser::imageDataChecksumFromPath(self::DATA_PATH . 'firstPodcast.xml'));
    }
}
