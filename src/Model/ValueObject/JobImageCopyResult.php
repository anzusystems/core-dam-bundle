<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\ValueObject;

use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use AnzuSystems\CoreDamBundle\Exception\DomainException;

final readonly class JobImageCopyResult implements ValueObjectInterface
{
    public function __construct(
        private int $copyCount = 0,
        private int $existsCount = 0,
        private int $notAllowedCount = 0,
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            '%d|%d|%d',
            $this->copyCount,
            $this->existsCount,
            $this->notAllowedCount,
        );
    }

    public static function fromString(string $string): self
    {
        $parts = explode('|', $string);

        return new self(
            (int) $parts[0],
            isset($parts[1]) ? (int) $parts[1] : 0,
            isset($parts[2]) ? (int) $parts[2] : 0,
        );
    }

    public function getCopyCount(): int
    {
        return $this->copyCount;
    }

    public function getExistsCount(): int
    {
        return $this->existsCount;
    }

    public function getNotAllowedCount(): int
    {
        return $this->notAllowedCount;
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
