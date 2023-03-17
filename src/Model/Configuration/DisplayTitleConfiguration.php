<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

final readonly class DisplayTitleConfiguration
{
    public function __construct(
        private array $image,
        private array $audio,
        private array $video,
        private array $document,
    ) {
    }

    public static function getFromArrayConfiguration(
        array $config
    ): self {
        return new self(
            array_map(
                fn (array $episodeMapConfig): TextsWriterConfiguration => TextsWriterConfiguration::getFromArrayConfiguration($episodeMapConfig),
                $config[AssetType::Image->toString()] ?? []
            ),
            array_map(
                fn (array $episodeMapConfig): TextsWriterConfiguration => TextsWriterConfiguration::getFromArrayConfiguration($episodeMapConfig),
                $config[AssetType::Audio->toString()] ?? []
            ),
            array_map(
                fn (array $episodeMapConfig): TextsWriterConfiguration => TextsWriterConfiguration::getFromArrayConfiguration($episodeMapConfig),
                $config[AssetType::Video->toString()] ?? []
            ),
            array_map(
                fn (array $episodeMapConfig): TextsWriterConfiguration => TextsWriterConfiguration::getFromArrayConfiguration($episodeMapConfig),
                $config[AssetType::Document->toString()] ?? []
            )
        );
    }

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getDisplayTitleConfig(AssetType $type): array
    {
        return match ($type) {
            AssetType::Image => $this->getImage(),
            AssetType::Video => $this->getVideo(),
            AssetType::Audio => $this->getAudio(),
            AssetType::Document => $this->getDocument(),
        };
    }

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getImage(): array
    {
        return $this->image;
    }

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getAudio(): array
    {
        return $this->audio;
    }

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getVideo(): array
    {
        return $this->video;
    }

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getDocument(): array
    {
        return $this->document;
    }
}
