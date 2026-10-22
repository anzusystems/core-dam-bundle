<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Image\Crop;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropCache;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropProcessor;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFileOptimalResize;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\MimeGuesser;
use AnzuSystems\CoreDamBundle\Image\Filter\CropFilter;
use AnzuSystems\CoreDamBundle\Image\Filter\FilterStack;
use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCropDto;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CropProcessorTest extends CoreDamKernelTestCase
{
    private const int ORIGIN_WIDTH = 6_000;
    private const int ORIGIN_HEIGHT = 4_000;
    private const string PATH_PREFIX = 'crop_processor_test_';

    /**
     * @var list<string>
     */
    private array $writtenPaths = [];
    private FileSystemProvider $fileSystemProvider;
    private ?string $loadedContent = null;
    private ?CropFilter $appliedCropFilter = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileSystemProvider = $this->getService(FileSystemProvider::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->writtenPaths as $path) {
            $this->fileSystemProvider->getFileSystemByStorageName('cms.image')?->delete($path);
        }
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{ImageCropDto, int, int, int}>
     */
    public static function cropProvider(): iterable
    {
        yield 'Narrow region needs the original size resize' => [
            new ImageCropDto(0, 0, 3_000, 2_000, 1_920, 1_280, 90),
            self::ORIGIN_WIDTH,
            self::ORIGIN_HEIGHT,
            self::ORIGIN_WIDTH,
        ];
        yield 'Wide region still needs the original size resize' => [
            new ImageCropDto(0, 0, 5_000, 3_333, 1_920, 1_280, 90),
            self::ORIGIN_WIDTH,
            self::ORIGIN_HEIGHT,
            self::ORIGIN_WIDTH,
        ];
        yield 'Full image keeps the first resize covering the output' => [
            new ImageCropDto(0, 0, self::ORIGIN_WIDTH, self::ORIGIN_HEIGHT, 1_920, 1_280, 90),
            self::ORIGIN_WIDTH,
            self::ORIGIN_HEIGHT,
            2_000,
        ];
        yield 'Full image with a small output uses the smallest resize' => [
            new ImageCropDto(0, 0, self::ORIGIN_WIDTH, self::ORIGIN_HEIGHT, 1_000, 667, 90),
            self::ORIGIN_WIDTH,
            self::ORIGIN_HEIGHT,
            1_200,
        ];
        yield 'Narrow region with a small output stays on the smallest resize' => [
            new ImageCropDto(0, 0, 3_000, 2_000, 600, 400, 90),
            self::ORIGIN_WIDTH,
            self::ORIGIN_HEIGHT,
            1_200,
        ];
        yield 'Full-width crop truncated by one pixel keeps the exact resize' => [
            new ImageCropDto(0, 367, 5_184, 2_721, 1_200, 630, 90),
            5_184,
            3_456,
            1_200,
        ];
    }

    #[DataProvider('cropProvider')]
    public function testApplyCropReadsSmallestResizeCoveringOutputWithItsRegion(
        ImageCropDto $imageCrop,
        int $originWidth,
        int $originHeight,
        int $expectedResizeWidth,
    ): void {
        $image = $this->buildImageFile($originWidth, $originHeight);

        $this->buildCropProcessor()->applyCrop($image, $imageCrop);

        self::assertSame(self::PATH_PREFIX . $expectedResizeWidth, $this->loadedContent);
        self::assertNotNull($this->appliedCropFilter);
        $ratio = $expectedResizeWidth / $originWidth;
        self::assertSame((int) ($imageCrop->getWidth() * $ratio), $this->appliedCropFilter->getWidth());
        self::assertGreaterThanOrEqual(
            min($imageCrop->getRequestWidth(), $imageCrop->getWidth()),
            $this->appliedCropFilter->getWidth()
        );
    }

    private function buildCropProcessor(): CropProcessor
    {
        $manipulator = $this->createStub(ImageManipulatorInterface::class);
        $manipulator->method('loadContent')->willReturnCallback(
            function (string $content): void {
                $this->loadedContent = $content;
            }
        );
        $manipulator->method('applyFilterStack')->willReturnCallback(
            function (FilterStack $filterStack): void {
                foreach ($filterStack->getFilters() as $filter) {
                    if ($filter instanceof CropFilter) {
                        $this->appliedCropFilter = $filter;
                    }
                }
            }
        );
        $manipulator->method('getContent')->willReturn('');

        $processor = new CropProcessor(
            $this->fileSystemProvider,
            $manipulator,
            $this->getService(CropCache::class),
            new ConfigurationProvider(
                [ConfigurationProvider::ENABLE_CROP_CACHE => false],
                [],
                [],
                $this->getService(AllowListConfiguration::class),
                [],
            ),
        );
        $processor->setFileHelper($this->getService(MimeGuesser::class));

        return $processor;
    }

    private function buildImageFile(int $originWidth, int $originHeight): ImageFile
    {
        $licence = $this->getService(AssetLicenceRepository::class)->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);
        self::assertNotNull($licence);

        $image = new ImageFile();
        $image->setLicence($licence);
        $image->getImageAttributes()
            ->setWidth($originWidth)
            ->setHeight($originHeight);

        foreach ([1_200, 2_000, $originWidth] as $width) {
            $resize = (new ImageFileOptimalResize())
                ->setImage($image)
                ->setRequestedSize($width)
                ->setWidth($width)
                ->setHeight((int) round($width * $originHeight / $originWidth))
                ->setFilePath(self::PATH_PREFIX . $width);
            $image->getResizes()->add($resize);
            $this->fileSystemProvider->getFilesystemByStorable($resize)->write(
                $resize->getFilePath(),
                $resize->getFilePath()
            );
            $this->writtenPaths[] = $resize->getFilePath();
        }

        return $image;
    }
}
