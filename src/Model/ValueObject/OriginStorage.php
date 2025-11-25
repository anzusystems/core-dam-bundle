<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\ValueObject;

use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use AnzuSystems\CoreDamBundle\Exception\DomainException;

final readonly class OriginStorage implements ValueObjectInterface
{
    public function __construct(
        private string $storageName = '',
        private string $path = '',
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            '%s|%s',
            $this->storageName,
            $this->path,
        );
    }

    public static function fromString(string $value): self
    {
        $parts = explode('|', $value, 3);

        return new self(
            $parts[0],
            $parts[1] ?? '',
        );
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function is(string $value): bool
    {
        return $this->toString() === $value;
    }

    public function isNot(string $value): bool
    {
        return false === $this->is($value);
    }

    public function in(array $values): bool
    {
        throw new DomainException('Method "in" not supported for OriginStorage.');
    }

    /**
     * @param OriginStorage $valueObject
     */
    public function equals(ValueObjectInterface $valueObject): bool
    {
        return $this->is($valueObject->toString());
    }

    public function toString(): string
    {
        return (string) $this;
    }
}
