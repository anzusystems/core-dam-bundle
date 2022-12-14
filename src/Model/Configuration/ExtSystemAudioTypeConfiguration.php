<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;

final class ExtSystemAudioTypeConfiguration extends ExtSystemAssetTypeConfiguration
{
    public const PODCAST_EPISODE_RSS_MAP_KEY = 'podcast_episode_rss_map';
    public const PODCAST_EPISODE_ENTITY_MAP_KEY = 'podcast_episode_entity_map';

    private array $podcastEpisodeRssMap;
    private array $podcastEpisodeEntityMap;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setPodcastEpisodeRssMap(
                array_map(
                    fn (array $episodeMapConfig): TextsWriterConfiguration => TextsWriterConfiguration::getFromArrayConfiguration($episodeMapConfig),
                    $config[self::PODCAST_EPISODE_RSS_MAP_KEY] ?? []
                )
            )
            ->setPodcastEpisodeEntityMap(
                array_map(
                    fn (array $episodeMapConfig): TextsWriterConfiguration => TextsWriterConfiguration::getFromArrayConfiguration($episodeMapConfig),
                    $config[self::PODCAST_EPISODE_ENTITY_MAP_KEY] ?? []
                )
            )
        ;
    }

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getPodcastEpisodeRssMap(): array
    {
        return $this->podcastEpisodeRssMap;
    }

    public function setPodcastEpisodeRssMap(array $podcastEpisodeRssMap): self
    {
        $this->podcastEpisodeRssMap = $podcastEpisodeRssMap;

        return $this;
    }

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getPodcastEpisodeEntityMap(): array
    {
        return $this->podcastEpisodeEntityMap;
    }

    public function setPodcastEpisodeEntityMap(array $podcastEpisodeEntityMap): self
    {
        $this->podcastEpisodeEntityMap = $podcastEpisodeEntityMap;

        return $this;
    }
}
