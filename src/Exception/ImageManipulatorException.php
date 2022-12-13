<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use Exception;
use Throwable;

final class ImageManipulatorException extends Exception
{
    public const ERROR_MESSAGE = 'image_manipulator_error';
    public const ERROR_FILE_CLOSED = 'image_manipulator_file_closed_error';
    public const ERROR_FILE_WRITE_FAILED = 'image_manipulator_file_write_failed';
    public const ERROR_FILE_READ_FAILED = 'image_manipulator_file_read_failed';
    public const ERROR_PROCESSOR_NOT_FOUND = 'image_manipulator_processor_not_found';

    public function __construct(string $errorMessage = self::ERROR_MESSAGE, Throwable $previous = null)
    {
        parent::__construct($errorMessage, 0, $previous);
    }
}
