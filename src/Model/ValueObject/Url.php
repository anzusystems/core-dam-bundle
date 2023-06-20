<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\ValueObject;

use InvalidArgumentException;

final class Url
{
    public function __construct(
        private readonly string $host,
        private readonly string $scheme,
        private readonly string $path,
        private readonly array $queryParams,
    ) {
    }

    public static function createFromArray(array $array): self
    {
        if (false === isset($array['host'])) {
            throw new InvalidArgumentException('Mandatory argument \'host\' is missing');
        }

        $queryParams = [];
        if (isset($array['query'])) {
            parse_str((string) $array['query'], $queryParams);
        }

        return new self(
            $array['host'],
            $array['scheme'] ?? 'http',
            $array['path'] ?? '',
            $queryParams,
        );
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getOrigin(): string
    {
        return "{$this->getScheme()}://{$this->getHost()}";
    }
}
