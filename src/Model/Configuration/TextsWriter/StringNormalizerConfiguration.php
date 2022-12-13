<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter;

final class StringNormalizerConfiguration
{
    public const TYPE = 'string';
    public const LENGTH_KEY = 'length';

    private ?int $length = null;

    public static function getFromArrayConfiguration(array $config): self
    {
        return (new self())
            ->setLength($config[self::LENGTH_KEY] ?? null)
        ;
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
}
