<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\ValueObject;

use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use AnzuSystems\CoreDamBundle\Exception\DomainException;

final class Color implements ValueObjectInterface
{
    private int $red;
    private int $green;
    private int $black;

    public function __construct(
        int $red = 0,
        int $green = 0,
        int $black = 0,
    ) {
        $this->validateSingleColor($red);
        $this->validateSingleColor($green);
        $this->validateSingleColor($black);

        $this->red = $red;
        $this->green = $green;
        $this->black = $black;
    }

    public static function fromString(string $color): self
    {
        list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
        if (null === $r || null === $g || null === $b) {
            return new self();
        }

        return new self($r, $g, $b);
    }

    public function __toString(): string
    {
        return sprintf(
            '#%02x%02x%02x',
            (int) round($this->red),
            (int) round($this->green),
            (int) round($this->black),
        );
    }

    public function getColorDist(self $color): int
    {
        return $this->getDist($color->getRed(), $color->getGreen(), $color->getBlack());
    }

    public function getDist(int $red, int $green, int $black): int
    {
        $redDist = $this->getRed() - $red;
        $greenDist = $this->getGreen() - $green;
        $blackDist = $this->getBlack() - $black;

        return (int) sqrt(($redDist * $redDist) + ($greenDist * $greenDist) + ($blackDist * $blackDist));
    }

    public function getRed(): int
    {
        return $this->red;
    }

    public function getGreen(): int
    {
        return $this->green;
    }

    public function getBlack(): int
    {
        return $this->black;
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
        throw new DomainException('Method "in" not supported for Color.');
    }

    /**
     * @param Color $valueObject
     */
    public function equals(ValueObjectInterface $valueObject): bool
    {
        return $this->getGreen() === $valueObject->getGreen() &&
            $this->getRed() === $valueObject->getRed() &&
            $this->getBlack() === $valueObject->getBlack();
    }

    public function toString(): string
    {
        return (string) $this;
    }

    private function validateSingleColor(int $color): void
    {
        if ($color >= 0 && $color <= 255) {
            return;
        }

        throw new DomainException('Invalid geolocation coordinates.');
    }
}
