<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use Exception;

final class InvalidCropException extends Exception
{
    public const string ERROR_MESSAGE = 'crop_not_supported';

    public function __construct(string $errorMessage = self::ERROR_MESSAGE)
    {
        parent::__construct($errorMessage);
    }
}
