<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class SettingsChunkConfiguration
{
    public const string MIN_SIZE_KEY = 'min_size';
    public const string MAX_SIZE_KEY = 'max_size';

    public function __construct(
        private readonly int $minSize,
        private readonly int $maxSize,
    ) {
    }

    public static function getFromArrayConfiguration(
        array $chunkConfig
    ): self {
        return new self(
            $chunkConfig[self::MIN_SIZE_KEY],
            $chunkConfig[self::MAX_SIZE_KEY],
        );
    }

    public function getMinSize(): int
    {
        return $this->minSize;
    }

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }
}
