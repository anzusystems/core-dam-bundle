<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\CommonBundle\Exception\ValidationException as BaseValidationException;

class ValidationException extends BaseValidationException
{
    public const string ERROR_INVALID_KEY = 'error_invalid_key';
    public const string ERROR_INVALID_LICENCE = 'error_invalid_licence';
    public const string ERROR_INVALID_EXT_SYSTEM = 'error_invalid_ext_system';
}
