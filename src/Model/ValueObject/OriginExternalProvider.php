<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\ValueObject;

use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use AnzuSystems\CoreDamBundle\Exception\DomainException;

final class OriginExternalProvider implements ValueObjectInterface
{
    public function __construct(
        private readonly string $providerName = '',
        private readonly string $id = '',
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            '%s|%s',
            $this->providerName,
            $this->id,
        );
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function getId(): string
    {
        return $this->id;
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
