<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\Exception;

use Exception;
use Throwable;

final class AnzuElasticSearchException extends Exception
{
    public function __construct(
        string $message,
        private readonly string $detail = '',
        private readonly array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(message: $message, previous: $previous);
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getBody(): array
    {
        return $this->body;
    }
}
