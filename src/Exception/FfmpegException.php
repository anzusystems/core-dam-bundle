<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use Exception;
use Throwable;

final class FfmpegException extends Exception
{
    public const ERROR_MESSAGE = 'ffmpg_failed';
    public const ERROR_READ_STREAM = 'error_read_stream';
    public const ERROR_FFPROBE = 'error_ffprobe';
    public const ERROR_UNSUPPORTED_MEDIA_TYPE = 'unsupported_media_type';

    public function __construct(string $errorMessage = self::ERROR_MESSAGE, Throwable $previous = null)
    {
        parent::__construct(message: $errorMessage, previous: $previous);
    }
}
