<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image;

use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Image\FilterProcessor\Stack\FilterProcessorStack;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\ValueObject\Color;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Jcupitt\Vips\Config;
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

    /** libvips ForeignKeep bitmask; ICC only, so GPS/EXIF/XMP/IPTC are dropped but colours stay intact. */
    private const int KEEP_ICC_ONLY = 8;

    /** `keep` replaced `strip` in libvips 8.15. Older builds reject it, newer ones accept `strip` and ignore it. */
    private const string KEEP_MIN_LIBVIPS_VERSION = '8.15';

    private ?Image $image = null;
    private int $quality;

    private static bool $initialized = false;

    public function __construct(
        FilterProcessorStack $filterProcessorStack,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly DamLogger $damLogger,
    ) {
        $this->setQuality(self::DEFAULT_QUALITY);

        parent::__construct($filterProcessorStack);
    }

    public function loadThumbnail(string $path, int $width): void
    {
        $this->disableCache();
        $this->image = Image::thumbnail($path, $width);
    }

    /**
     * @throws ImageManipulatorException
     */
    public function loadFile(string $scrPath): void
    {
        try {
            $this->disableCache();
            $this->image = Image::newFromFile($scrPath);
        } catch (Exception $exception) {
            throw new ImageManipulatorException(ImageManipulatorException::ERROR_FILE_READ_FAILED, $exception);
        }
    }

    public function loadContent(string $resource): void
    {
        try {
            $this->disableCache();
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
            $this->clean($clean);

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

    // Crop path only; originals and resizes go through writeToFile() and keep their metadata.
    public function getContent(string $extension, bool $clean = true): string
    {
        $this->ensureImage();

        try {
            $content = $this->image->writeToBuffer('.' . $extension, $this->getStripSaveOptions());
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
        $this->ensureImage();
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

    public function clean(bool $clean = true): void
    {
        if (true === $clean) {
            $this->image = null;
        }
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

    // Pre-8.15 libvips rejects `keep` (each crop would 500) — skip strip there; CDN strips public output anyway.
    private function getStripSaveOptions(): array
    {
        $options = ['Q' => $this->quality];

        if (version_compare(Config::version(), self::KEEP_MIN_LIBVIPS_VERSION, '>=')) {
            $options['keep'] = self::KEEP_ICC_ONLY;
        }

        return $options;
    }

    private function disableCache(): void
    {
        if (self::$initialized) {
            return;
        }

        Config::CacheSetMax(0);
        Config::CacheSetMaxFiles(0);
        Config::CacheSetMaxMem(0);
        // No ConcurrencySet: thread count is deployment policy — cap via VIPS_CONCURRENCY env in the pod spec.
        self::$initialized = true;
    }
}
