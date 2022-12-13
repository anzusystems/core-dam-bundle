<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\YoutubeDistribution;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\PlaylistDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeLanguageDto;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

final class YoutubeDataStorage
{
    use SerializerAwareTrait;

    private const PLAYLIST_KEY = 'playlist';
    private const LANAGUAGE_KEY = 'language';

    public function __construct(
        private readonly CacheItemPoolInterface $coreDamBundleYoutubeCache,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function storePlaylists(array $playlists, string $serviceId): void
    {
        $this->store($playlists, $this->createKey($serviceId, self::PLAYLIST_KEY));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function hasPlaylist(string $serviceId): bool
    {
        return $this->coreDamBundleYoutubeCache->hasItem($this->createKey($serviceId, self::PLAYLIST_KEY));
    }

    /**
     * @return array<int, PlaylistDto>
     *
     * @throws InvalidArgumentException
     */
    public function getPlaylists(string $serviceId): array
    {
        $item = $this->coreDamBundleYoutubeCache->getItem($this->createKey($serviceId, self::PLAYLIST_KEY));
        if ($item->isHit()) {
            return $item->get();
        }

        return [];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function storeLanguages(array $languages, string $regionCode): void
    {
        $this->store($languages, $this->createKey($regionCode, self::LANAGUAGE_KEY));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function hasLanguages(string $regionCode): bool
    {
        return $this->coreDamBundleYoutubeCache->hasItem($this->createKey($regionCode, self::LANAGUAGE_KEY));
    }

    /**
     * @return array<int, YoutubeLanguageDto>
     *
     * @throws InvalidArgumentException
     */
    public function getLanguages(string $regionCode): array
    {
        $item = $this->coreDamBundleYoutubeCache->getItem($this->createKey($regionCode, self::LANAGUAGE_KEY));
        if ($item->isHit()) {
            return $item->get();
        }

        return [];
    }

    public function createKey(string $service, string $type): string
    {
        return "youtube_{$service}_{$type}";
    }

    /**
     * @throws InvalidArgumentException
     */
    private function store(mixed $value, string $key): void
    {
        $item = $this->coreDamBundleYoutubeCache->getItem($key);
        $item->set($value);
        $this->coreDamBundleYoutubeCache->save($item);
    }
}
