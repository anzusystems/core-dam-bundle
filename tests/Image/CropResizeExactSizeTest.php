<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Image;

use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropFactory;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Image\Filter\CropFilter;
use AnzuSystems\CoreDamBundle\Image\Filter\FilterStack;
use AnzuSystems\CoreDamBundle\Image\Filter\ResizeFilter;
use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use Jcupitt\Vips\Image;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The public crop URL promises `w{W}-h{H}`; CropFactory truncates the crop rectangle to whole pixels,
 * so its aspect ratio is never exactly W:H and the resize step must force the requested size instead
 * of trusting the ratio. Every case is a plain numeric input: image size, ROI, requested size.
 */
final class CropResizeExactSizeTest extends CoreDamKernelTestCase
{
    private ImageManipulatorInterface $imageManipulator;
    private CropFactory $cropFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageManipulator = $this->getService(ImageManipulatorInterface::class);
        $this->cropFactory = new CropFactory();
    }

    #[DataProvider('cropProvider')]
    public function testCropAndResizeYieldExactlyTheRequestedSize(
        int $originWidth,
        int $originHeight,
        float $roiWidthPercentage,
        float $roiHeightPercentage,
        int $roiPointX,
        int $roiPointY,
        int $requestWidth,
        int $requestHeight,
    ): void {
        $image = new ImageFile();
        $image->getImageAttributes()
            ->setWidth($originWidth)
            ->setHeight($originHeight);
        $roi = (new RegionOfInterest())
            ->setPointX($roiPointX)
            ->setPointY($roiPointY)
            ->setPercentageWidth($roiWidthPercentage)
            ->setPercentageHeight($roiHeightPercentage);
        $cropPayload = (new RequestedCropDto())
            ->setRequestWidth($requestWidth)
            ->setRequestHeight($requestHeight);

        $crop = $this->cropFactory->prepareImageCrop($roi, $cropPayload, $image);

        $this->imageManipulator->loadContent(Image::black($originWidth, $originHeight)->writeToBuffer('.jpg'));
        $this->imageManipulator->applyFilterStack(new FilterStack([
            new CropFilter($crop->getPointX(), $crop->getPointY(), $crop->getWidth(), $crop->getHeight()),
            new ResizeFilter($crop->getRequestWidth(), $crop->getRequestHeight()),
        ]));

        self::assertSame(
            [$requestWidth, $requestHeight],
            [$this->imageManipulator->getWidth(), $this->imageManipulator->getHeight()],
            sprintf('crop %dx%d at %d,%d', $crop->getWidth(), $crop->getHeight(), $crop->getPointX(), $crop->getPointY()),
        );
    }

    public static function cropProvider(): array
    {
        return [
            'TASR portrait 1390x2000, full ROI, w1200-h630 (crop 1390x729 -> 1201 without force)' => [1390, 2000, 1.0, 1.0, 0, 0, 1200, 630],
            'TASR portrait 1390x2000, full ROI, w800-h450 (crop 1390x781 -> 801 without force)' => [1390, 2000, 1.0, 1.0, 0, 0, 800, 450],
            'small ROI 565x297 inside 1600x900, upscaled to w1200-h630 (-> 1198 without force)' => [1600, 900, 0.33, 0.33, 700, 300, 1200, 630],
            'landscape 6000x4000, full ROI, w1200-h630 (ratio already exact)' => [6000, 4000, 1.0, 1.0, 0, 0, 1200, 630],
            '800x600 upscaled to w1200-h630 (ratio exact, upscaling must be allowed)' => [800, 600, 1.0, 1.0, 0, 0, 1200, 630],
        ];
    }
}
