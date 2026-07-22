<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Image;

use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;

// End-to-end on real bytes: libvips silently ignores `strip` since 8.15, so only output proves the strip.
final class VispImageManipulatorTest extends CoreDamKernelTestCase
{
    private const string FIXTURE = __DIR__ . '/../data/Files/exifGps.jpg';

    private ImageManipulatorInterface $imageManipulator;
    private Exiftool $exiftool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageManipulator = $this->getService(ImageManipulatorInterface::class);
        $this->exiftool = $this->getService(Exiftool::class);
    }

    public function testFixtureCarriesGpsAuthorAndColourProfile(): void
    {
        $tags = $this->exiftool->getTags(self::FIXTURE);

        self::assertArrayHasKey('GPSLatitude', $tags);
        self::assertSame('Tajny Fotograf', $tags['Artist'] ?? null);
        self::assertArrayHasKey('ProfileDescription', $tags);
    }

    public function testCropContentDropsGpsAndAuthor(): void
    {
        $this->imageManipulator->loadFile(self::FIXTURE);
        $tags = $this->exiftool->getTags($this->writeTmp($this->imageManipulator->getContent('jpg')));

        self::assertArrayNotHasKey('GPSLatitude', $tags);
        self::assertArrayNotHasKey('GPSLongitude', $tags);
        self::assertArrayNotHasKey('Artist', $tags);
    }

    public function testCropContentKeepsColourProfile(): void
    {
        $this->imageManipulator->loadFile(self::FIXTURE);
        $tags = $this->exiftool->getTags($this->writeTmp($this->imageManipulator->getContent('jpg')));

        self::assertArrayHasKey('ProfileDescription', $tags);
    }

    public function testWriteToFileLeavesOriginalMetadataIntact(): void
    {
        $target = sys_get_temp_dir() . '/' . uniqid('visp_original_', true) . '.jpg';
        $this->imageManipulator->loadFile(self::FIXTURE);
        $this->imageManipulator->writeToFile($target);

        $tags = $this->exiftool->getTags($target);
        unlink($target);

        self::assertArrayHasKey('GPSLatitude', $tags);
        self::assertSame('Tajny Fotograf', $tags['Artist'] ?? null);
    }

    private function writeTmp(string $content): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('visp_crop_', true) . '.jpg';
        file_put_contents($path, $content);

        return $path;
    }
}
