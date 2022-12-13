<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\FilterProcessor;

use AnzuSystems\CoreDamBundle\Image\ImageManipulatorInterface;

abstract class AbstractFilterProcessor implements FilterProcessorInterface
{
    protected ImageManipulatorInterface $imageManipulator;

    public function __construct(ImageManipulatorInterface $imageManipulator)
    {
        $this->imageManipulator = $imageManipulator;
    }
}
