<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Image;

use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use Jcupitt\Vips\Image;

final class VispImageManipulatorTest extends CoreDamKernelTestCase
{
    private string $path;
    private ImageManipulatorInterface $imageManipulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageManipulator = $this->getService(ImageManipulatorInterface::class);
        $this->path = sys_get_temp_dir() . '/visp_image_manipulator_test_' . uniqid() . '.jpg';
        Image::black(1_200, 800)->writeToFile($this->path);
    }

    protected function tearDown(): void
    {
        $this->imageManipulator->releaseRetained();
        unlink($this->path);
        parent::tearDown();
    }

    public function testRetainedImageIsResizedWithoutReadingTheFileAgain(): void
    {
        $this->imageManipulator->loadThumbnail($this->path, 500);
        $this->imageManipulator->retain();
        $this->imageManipulator->loadRetainedThumbnail(100);

        self::assertSame(100, $this->imageManipulator->getWidth());
        self::assertSame(67, $this->imageManipulator->getHeight());
    }

    public function testResizeReturnsTheRequestedSizeExactly(): void
    {
        $this->imageManipulator->loadThumbnail($this->path, 1_200);
        $this->imageManipulator->crop(0, 0, 1_000, 563);
        $this->imageManipulator->resize(500, 281);

        self::assertSame(500, $this->imageManipulator->getWidth());
        self::assertSame(281, $this->imageManipulator->getHeight());
    }

    public function testReleasedRetainedImageCannotBeResized(): void
    {
        $this->imageManipulator->loadThumbnail($this->path, 500);
        $this->imageManipulator->retain();
        $this->imageManipulator->releaseRetained();

        $this->expectException(ImageManipulatorException::class);
        $this->expectExceptionMessage(ImageManipulatorException::ERROR_FILE_CLOSED);

        $this->imageManipulator->loadRetainedThumbnail(50);
    }
}
