<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Image\FileProcessor;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\OptimalCropsProcessor;
use AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize\OptimalResizeFactory;
use AnzuSystems\CoreDamBundle\Domain\ImageFileOptimalResize\OptimalResizeManager;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFileOptimalResize;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\MimeGuesser;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use Jcupitt\Vips\Image;
use PHPUnit\Framework\Attributes\DataProvider;

final class OptimalCropsProcessorTest extends CoreDamKernelTestCase
{
    private const string ORIGIN_NAME = 'optimal_crops_processor_test.jpg';

    private FileSystemProvider $fileSystemProvider;
    private string $originDir;
    private ImageFile $image;
    private int $originWidth;
    private int $originHeight;
    private int $retainCalls = 0;

    /**
     * @var list<array{string, int}>
     */
    private array $decodedFromFile = [];

    /**
     * @var list<int>
     */
    private array $decodedFromMemory = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileSystemProvider = $this->getService(FileSystemProvider::class);
        $this->originDir = sys_get_temp_dir() . '/optimal_crops_processor_test_' . uniqid();
        mkdir($this->originDir);
    }

    protected function tearDown(): void
    {
        foreach ($this->image->getResizes() as $resize) {
            $this->fileSystemProvider->getFilesystemByStorable($this->image)->delete($resize->getFilePath());
        }
        unlink($this->originDir . '/' . self::ORIGIN_NAME);
        rmdir($this->originDir);
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{int, int, list<array{int}>, list<int>, list<int>, int}>
     */
    public static function originProvider(): iterable
    {
        yield 'Several sizes cascade from the largest' => [1_200, 800, [[1_200], [500]], [100, 50], [1_200, 50, 100, 500], 1];
        yield 'A single size is decoded from the file' => [120, 80, [[120], [50]], [], [120, 50], 0];
        yield 'No size fits' => [40, 30, [[40]], [], [40], 0];
    }

    /**
     * @param list<array{int}> $expectedFromFileWidths
     * @param list<int> $expectedFromMemory
     * @param list<int> $expectedOrder
     */
    #[DataProvider('originProvider')]
    public function testSmallerResizesAreMadeFromTheLargestOneInMemory(
        int $originWidth,
        int $originHeight,
        array $expectedFromFileWidths,
        array $expectedFromMemory,
        array $expectedOrder,
        int $expectedRetainCalls,
    ): void {
        $this->originWidth = $originWidth;
        $this->originHeight = $originHeight;
        $path = $this->originDir . '/' . self::ORIGIN_NAME;
        Image::black($originWidth, $originHeight)->writeToFile($path);
        $this->image = $this->buildImageFile();
        $file = new AdapterFile(
            path: $path,
            adapterPath: self::ORIGIN_NAME,
            filesystem: $this->fileSystemProvider->createLocalFilesystem($this->originDir),
        );

        $this->buildProcessor()->process($this->image, $file);

        // The configured resizes in the test env are 500, 100 and 50; only the main crop and the largest one decode the file.
        self::assertSame(
            array_map(static fn (array $widths): array => [$path, $widths[0]], $expectedFromFileWidths),
            $this->decodedFromFile
        );
        self::assertSame($expectedFromMemory, $this->decodedFromMemory);
        self::assertSame($expectedRetainCalls, $this->retainCalls);
        self::assertSame(
            $expectedOrder,
            array_map(
                static fn (ImageFileOptimalResize $resize): int => $resize->getRequestedSize(),
                $this->image->getResizes()->toArray()
            )
        );
    }

    private function buildProcessor(): OptimalCropsProcessor
    {
        $manipulator = $this->createStub(ImageManipulatorInterface::class);
        $manipulator->method('loadThumbnail')->willReturnCallback(
            function (string $path, int $width): void {
                $this->decodedFromFile[] = [$path, $width];
            }
        );
        $manipulator->method('retain')->willReturnCallback(
            function (): void {
                ++$this->retainCalls;
            }
        );
        $manipulator->method('loadRetainedThumbnail')->willReturnCallback(
            function (int $width): void {
                $this->decodedFromMemory[] = $width;
            }
        );
        $manipulator->method('writeToFile')->willReturnCallback(
            static function (string $dstFile): void {
                file_put_contents($dstFile, 'resize');
            }
        );
        $manipulator->method('getWidth')->willReturn($this->originWidth);
        $manipulator->method('getHeight')->willReturn($this->originHeight);

        $factory = new OptimalResizeFactory(
            $this->fileSystemProvider,
            $this->getService(NameGenerator::class),
            $manipulator,
            $this->getService(OptimalResizeManager::class),
        );
        $factory->setFileHelper($this->getService(MimeGuesser::class));

        return new OptimalCropsProcessor(
            $this->getService(ConfigurationProvider::class),
            $factory,
            $this->fileSystemProvider,
        );
    }

    private function buildImageFile(): ImageFile
    {
        $licence = $this->getService(AssetLicenceRepository::class)->find(AssetLicenceFixtures::LICENCE_ID);
        self::assertNotNull($licence);

        $image = new ImageFile();
        $image->setLicence($licence);
        $image->getAssetAttributes()->setFilePath('optimal_crops_processor_test/' . self::ORIGIN_NAME);

        return $image;
    }
}
