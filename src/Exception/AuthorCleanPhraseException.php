<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use Exception;
use Throwable;

class AuthorCleanPhraseException extends Exception
{
    public const string ERROR_INVALID_MODE_AND_COMBINATION = 'error_invalid_mode_and_type_combination';
    public const string ERROR_CACHE_BUILDER_MISSING = 'error_cache_builder_missing';

    public function __construct(
        string $message,
        private readonly string $detail,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }

    public function getDetail(): string
    {
        return $this->detail;
    }
}
