<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final class DisplayTitleConfiguration
{
    public function __construct(
        private readonly array $image,
        private readonly array $audio,
        private readonly array $video,
        private readonly array $document,
    ) {
    }

    public static function getFromArrayConfiguration(
        array $config
    ): self {
        return new self(
            $config[AssetType::Image->toString()] ?? [],
            $config[AssetType::Audio->toString()] ?? [],
            $config[AssetType::Video->toString()] ?? [],
            $config[AssetType::Document->toString()] ?? [],
        );
    }

    public function getImage(): array
    {
        return $this->image;
    }

    public function getAudio(): array
    {
        return $this->audio;
    }

    public function getVideo(): array
    {
        return $this->video;
    }

    public function getDocument(): array
    {
        return $this->document;
    }
}
