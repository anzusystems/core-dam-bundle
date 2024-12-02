<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\ValueObject;

use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use AnzuSystems\CoreDamBundle\Exception\DomainException;

final readonly class JobAuthorCurrentOptimizeResult implements ValueObjectInterface
{
    public function __construct(
        private int $optimizedCount = 0,
        private int $totalCount = 0,
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            '%d|%d',
            $this->optimizedCount,
            $this->totalCount,
        );
    }

    public static function fromString(string $string): self
    {
        $parts = explode('|', $string);

        return new self(
            isset($parts[0]) ? (int) $parts[0] : 0,
            isset($parts[1]) ? (int) $parts[1] : 0,
        );
    }

    public function getOptimizedCount(): int
    {
        return $this->optimizedCount;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
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
        throw new DomainException('Method "in" not supported for OriginExternalProvider.');
    }

    /**
     * @param OriginExternalProvider $valueObject
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
