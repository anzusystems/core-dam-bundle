<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\TextsWriter\TextsWriterConfiguration;

final class ExtSystemAudioTypeConfiguration extends ExtSystemAssetTypeConfiguration
{
    public const PODCAST_EPISODE_RSS_MAP_KEY = 'podcast_episode_rss_map';
    public const PODCAST_EPISODE_ENTITY_MAP_KEY = 'podcast_episode_entity_map';
    public const AUDIO_PUBLIC_STORAGE = 'public_storage';
    public const PUBLIC_DOMAIN_NAME = 'public_domain_name';

    private array $podcastEpisodeRssMap;
    private array $podcastEpisodeEntityMap;
    private string $publicStorage;
    private string $publicDomainName;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setPublicStorage($config[self::AUDIO_PUBLIC_STORAGE] ?? '')
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
            ->setPublicDomainName(
                $config[self::PUBLIC_DOMAIN_NAME] ?? ''
            )
        ;
    }

    public function getPublicDomainName(): string
    {
        return $this->publicDomainName;
    }

    public function setPublicDomainName(string $publicDomainName): self
    {
        $this->publicDomainName = $publicDomainName;
        return $this;
    }

    public function getPublicStorage(): string
    {
        return $this->publicStorage;
    }

    public function setPublicStorage(string $publicStorage): self
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
}
