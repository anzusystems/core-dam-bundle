<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use Exception;

class InvalidMimeTypeException extends Exception
{
    public const ERROR_MESSAGE = 'invalid_mime_type';
    public const ERROR_EXTENSION_GUESS_FAILED = 'extension_guess_failed';

    public function __construct(
        private readonly string $mimeType,
        string $message = self::ERROR_MESSAGE
    ) {
        parent::__construct($message);
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }
}
