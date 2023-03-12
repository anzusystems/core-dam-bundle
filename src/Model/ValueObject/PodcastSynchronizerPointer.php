<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\ValueObject;

use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use DateTimeImmutable;
use DateTimeInterface;

final readonly class PodcastSynchronizerPointer implements ValueObjectInterface
{
    public function __construct(
        private ?string $podcastId = null,
        private ?DateTimeImmutable $pubDate = null
    ) {
    }

    public static function fromString(string $string): self
    {
        $parts = explode('|', $string);

        if (isset($parts[0], $parts[1])) {
            return (new self(
                $parts[0],
                DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $parts[1])
            ));
        }

        return (new self());
    }

    public function __toString(): string
    {
        return sprintf(
            '%s|%s',
            $this->podcastId,
            $this->pubDate->format(DateTimeInterface::ATOM),
        );
    }

    public function getPodcastId(): ?string
    {
        return $this->podcastId;
    }

    public function getPubDate(): ?DateTimeImmutable
    {
        return $this->pubDate;
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
