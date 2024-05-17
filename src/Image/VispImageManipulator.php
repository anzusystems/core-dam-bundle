<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image;

use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Image\FilterProcessor\Stack\FilterProcessorStack;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\ValueObject\Color;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Jcupitt\Vips\Exception;
use Jcupitt\Vips\Image;
use League\Flysystem\FilesystemException;
use Throwable;

/**
 * @psalm-suppress PossiblyNullReference
 * @psalm-suppress PossiblyNullPropertyFetch
 */
final class VispImageManipulator extends AbstractImageManipulator
{
    private const int N_BINS = 10;
    private const int BIN_SIZE = 256;
    private const int DEFAULT_QUALITY = 100;

    private ?Image $image = null;
    private int $quality;

    public function __construct(
        FilterProcessorStack $filterProcessorStack,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly DamLogger $damLogger,
    ) {
        $this->setQuality(self::DEFAULT_QUALITY);

        parent::__construct($filterProcessorStack);
    }

    /**
     * @throws ImageManipulatorException
     */
    public function loadFile(string $scrPath): void
    {
        try {
            $this->image = Image::newFromFile($scrPath);
        } catch (Exception $exception) {
            throw new ImageManipulatorException(ImageManipulatorException::ERROR_FILE_READ_FAILED, $exception);
        }
    }

    public function loadContent(string $resource): void
    {
        try {
            $this->image = Image::newFromBuffer($resource);
        } catch (Exception $exception) {
            throw new ImageManipulatorException(ImageManipulatorException::ERROR_FILE_READ_FAILED, $exception);
        }
    }

    public function isAnimated(): bool
    {
        try {
            $pages = $this->image->get('n-pages');
            if (is_int($pages) && $pages > 1) {
                return true;
            }

            return false;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @throws SerializerException
     *
     * @see https://github.com/libvips/php-vips/issues/92
     */
    public function getMostDominantColor(bool $clean = true): Color
    {
        try {
            $this->ensureImage();

            $hist = $this->image->hist_find_ndim(['bins' => self::N_BINS]);
            [$v, $x, $y] = $hist->maxpos();

            $pixel = $hist->getpoint($x, $y);
            /** @var int $z */
            $z = array_search($v, $pixel, true);

            $r = ($x + 0.5) * self::BIN_SIZE / self::N_BINS;
            $g = ($y + 0.5) * self::BIN_SIZE / self::N_BINS;
            $b = ($z + 0.5) * self::BIN_SIZE / self::N_BINS;
            $this->clean($clean);

            return new Color(
                (int) round($r),
                (int) round($g),
                (int) round($b),
            );
        } catch (Throwable) {
            $this->damLogger->info(
                DamLogger::NAMESPACE_VISP,
                'Failed compute most dominant color',
            );

            return new Color();
        }
    }

    /**
     * @throws ImageManipulatorException
     */
    public function writeToFile(string $dstFile, bool $clean = true): void
    {
        $this->ensureImage();

        try {
            $this->image->writeToFile($dstFile, ['Q' => $this->quality]);
        } catch (Exception $exception) {
            throw new ImageManipulatorException(ImageManipulatorException::ERROR_FILE_WRITE_FAILED, $exception);
        }
        $this->clean($clean);
    }

    public function getContent(string $extension, bool $clean = true): string
    {
        $this->ensureImage();

        try {
            $content = $this->image->writeToBuffer('.' . $extension, ['Q' => $this->quality]);
            $this->clean($clean);

            return $content;
        } catch (Exception $exception) {
            throw new ImageManipulatorException(ImageManipulatorException::ERROR_FILE_WRITE_FAILED, $exception);
        }
    }

    /**
     * @throws FilesystemException
     * @throws ImageManipulatorException
     */
    public function getStream(string $extension)
    {
        $this->ensureImage();

        $fileSystem = $this->fileSystemProvider->getTmpFileSystem();
        $tmpFilePath = $fileSystem->getTmpFileName();
        $this->image->writeToFile($fileSystem->extendPath($tmpFilePath));

        return $fileSystem->readStream($tmpFilePath);
    }

    /**
     * @throws ImageManipulatorException
     */
    public function resize(int $width, int $height): void
    {
        $this->ensureImage();
        $scale = $height / (int) $this->image->height;
        $this->image = $this->image->resize($scale);
    }

    /**
     * @throws ImageManipulatorException
     */
    public function rotate(float $angle): void
    {
        $this->ensureImage();

        if (90.0 === $angle) {
            $this->image = $this->image->rot90();

            return;
        }
        if (270.0 === $angle) {
            $this->image = $this->image->rot270();

            return;
        }
        if (180.0 === $angle) {
            $this->image = $this->image->rot180();

            return;
        }

        $this->image = $this->image->rotate($angle);
    }

    public function autorotate(array $options = []): void
    {
        $this->image = $this->image->autorot($options);
    }

    /**
     * @throws ImageManipulatorException
     */
    public function crop(int $pointX, int $pointY, int $width, int $height): void
    {
        $this->ensureImage();
        $this->image = $this->image->crop($pointX, $pointY, $width, $height);
    }

    public function setQuality(int $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    /**
     * @throws ImageManipulatorException
     */
    public function getWidth(): int
    {
        $this->ensureImage();

        return (int) $this->image->width;
    }

    /**
     * @throws ImageManipulatorException
     */
    public function getHeight(): int
    {
        $this->ensureImage();

        return (int) $this->image->height;
    }

    public function loadThumbnail(string $path, int $width): void
    {
        $this->image = Image::thumbnail($path, $width);
    }

    /**
     * @throws ImageManipulatorException
     */
    private function ensureImage(): void
    {
        if (null === $this->image) {
            throw new ImageManipulatorException(ImageManipulatorException::ERROR_FILE_CLOSED);
        }
    }

    private function clean(bool $clean = true): void
    {
        if (true === $clean) {
            $this->image = null;
        }
    }
}
