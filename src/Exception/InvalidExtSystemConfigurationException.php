<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use DomainException;

class InvalidExtSystemConfigurationException extends DomainException
{
    public const ERROR_MESSAGE = 'invalid_ext_system_configuration';

    public function __construct(
        private readonly string $detail
    ) {
        parent::__construct(self::ERROR_MESSAGE);
    }

    public function getDetail(): string
    {
        return $this->detail;
    }
}
