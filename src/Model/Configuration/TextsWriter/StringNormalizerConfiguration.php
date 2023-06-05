<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter;

final class StringNormalizerConfiguration
{
    public const TYPE = 'string';
    public const LENGTH_KEY = 'length';
    public const EMPTY_STRING = 'empty_string';
    public const TRIM = 'trim';

    private ?int $length = null;
    private bool $emptyString = false;
    private bool $trim = false;

    public static function getFromArrayConfiguration(array $config): self
    {
        return (new self())
            ->setLength($config[self::LENGTH_KEY] ?? null)
            ->setEmptyString($config[self::EMPTY_STRING] ?? false)
            ->setTrim($config[self::TRIM] ?? false)
        ;
    }

    public function isTrim(): bool
    {
        return $this->trim;
    }

    public function setTrim(bool $trim): self
    {
        $this->trim = $trim;

        return $this;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function setLength(?int $length): self
    {
        $this->length = $length;

        return $this;
    }

    public function setEmptyString(bool $emptyString): self
    {
        $this->emptyString = $emptyString;

        return $this;
    }

    public function isEmptyString(): bool
    {
        return $this->emptyString;
    }
}
