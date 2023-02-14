<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;

final class ExtSystemVideoTypeConfiguration extends ExtSystemAssetTypeConfiguration
{
    public const VIDEO_EPISODE_ENTITY_MAP_KEY = 'video_episode_entity_map';

    private array $videoEpisodeEntityMap;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setVideoEpisodeEntityMap(
                array_map(
                    fn (array $episodeMapConfig): TextsWriterConfiguration => TextsWriterConfiguration::getFromArrayConfiguration($episodeMapConfig),
                    $config[self::VIDEO_EPISODE_ENTITY_MAP_KEY] ?? []
                )
            )
        ;
    }

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getVideoEpisodeEntityMap(): array
    {
        return $this->videoEpisodeEntityMap;
    }

    public function setVideoEpisodeEntityMap(array $videoEpisodeEntityMap): self
    {
        $this->videoEpisodeEntityMap = $videoEpisodeEntityMap;

        return $this;
    }
}
