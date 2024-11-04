<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image;

use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Image\Filter\FilterStack;

interface ImageManipulatorInterface
{
    public function resize(int $width, int $height): void;

    public function rotate(float $angle): void;

    public function autorotate(): void;

    public function crop(int $pointX, int $pointY, int $width, int $height): void;

    public function loadThumbnail(string $path, int $width): void;

    public function setQuality(int $quality): self;

    /**
     * @throws ImageManipulatorException
     */
    public function getWidth(): int;

    /**
     * @throws ImageManipulatorException
     */
    public function getHeight(): int;

    /**
     * @throws ImageManipulatorException
     */
    public function loadFile(string $scrPath): void;

    /**
     * @throws ImageManipulatorException
     */
    public function loadContent(string $resource): void;

    /**
     * @throws ImageManipulatorException
     */
    public function writeToFile(string $dstFile, bool $clean = true): void;

    /**
     * @throws ImageManipulatorException
     */
    public function getContent(string $extension, bool $clean = true): string;

    /**
     * @return resource
     */
    public function getStream(string $extension);

    /**
     * @throws ImageManipulatorException
     */
    public function applyFilterStack(FilterStack $filterStack): void;

    public function clean(bool $clean = true): void;
}
