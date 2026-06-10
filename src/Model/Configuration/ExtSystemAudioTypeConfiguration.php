<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;

final class ExtSystemAudioTypeConfiguration extends ExtSystemAssetTypeConfiguration implements
    AssetFileRouteConfigurableInterface,
    AssetFileRoutePublicStorageInterface
{
    public const string PODCAST_EPISODE_RSS_MAP_KEY = 'podcast_episode_rss_map';
    public const string PODCAST_EPISODE_ENTITY_MAP_KEY = 'podcast_episode_entity_map';
    public const string TTS_METADATA_MAP_KEY = 'tts_metadata_map';
    public const string AUDIO_PUBLIC_STORAGE = 'public_storage';
    public const string PUBLIC_DOMAIN_NAME = 'public_domain_name';

    private array $podcastEpisodeRssMap;
    private array $podcastEpisodeEntityMap;
    private array $ttsMetadataMap;
    private string $publicStorage;
    private string $publicDomain;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setPublicStorage($config[self::AUDIO_PUBLIC_STORAGE] ?? '')
            ->setTtsMetadataMap(
                array_map(
                    fn (array $mapConfig): TextsWriterConfiguration => TextsWriterConfiguration::getFromArrayConfiguration($mapConfig),
                    $config[self::TTS_METADATA_MAP_KEY] ?? []
                )
            )
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
            ->setPublicDomain(
                $config[self::PUBLIC_DOMAIN_NAME] ?? ''
            );
    }

    public function getPublicDomain(): string
    {
        return $this->publicDomain;
    }

    public function setPublicDomain(string $publicDomain): static
    {
        $this->publicDomain = $publicDomain;

        return $this;
    }

    public function getPublicStorage(): string
    {
        return $this->publicStorage;
    }

    public function setPublicStorage(string $publicStorage): static
    {
        $this->publicStorage = $publicStorage;

        return $this;
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

    /**
     * @return array<int, TextsWriterConfiguration>
     */
    public function getTtsMetadataMap(): array
    {
        return $this->ttsMetadataMap;
    }

    public function setTtsMetadataMap(array $ttsMetadataMap): self
    {
        $this->ttsMetadataMap = $ttsMetadataMap;

        return $this;
    }
}
