<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Configuration;

use AnzuSystems\CoreDamBundle\Model\Configuration\AllowListMapConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\CacheConfiguration;
use AnzuSystems\CoreDamBundle\Model\Configuration\CropAllowListConfiguration;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\CropAllowItem;
use DomainException;
use Symfony\Component\HttpFoundation\RequestStack;

final class AllowListConfiguration
{
    public const string CROP_ALLOW_ITEM_WIDTH = 'width';
    public const string CROP_ALLOW_ITEM_HEIGHT = 'height';
    public const string CROP_ALLOW_ITEM_TITLE = 'title';

    private array $taggedListCache = [];

    public function __construct(
        private readonly array $taggedAllowList,
        private readonly array $domains,
        private readonly array $domainAllowList,
        private readonly array $domainNames,
        private readonly array $domainAllowMap,
        private readonly array $extSystemAllowListMap,
        private readonly DomainProvider $domainProvider,
    ) {
    }

    public function getCacheConfiguration(?string $domainName = null): CacheConfiguration
    {
        $domainName = $domainName ?? $this->getDomainName();
        if (isset($this->domains[$domainName])) {
            return CacheConfiguration::getFromArrayConfiguration($this->domains[$domainName]);
        }

        throw new DomainException("Domain ({$domainName}) not supported");
    }

    public function getSlugAllowLists(string $extSystemSlug): array
    {
        if (isset($this->extSystemAllowListMap[$extSystemSlug])) {
            return array_map(
                fn (array $config): AllowListMapConfiguration => AllowListMapConfiguration::getFromArrayConfiguration($config),
                $this->extSystemAllowListMap[$extSystemSlug]
            );
        }

        return [];
    }

    public function getListByDomain(string $extSystemSlug, ?string $domain = null): CropAllowListConfiguration
    {
        $schemeAndHost = $this->domainProvider->getSchemeAndHost($domain);
        $key = sprintf('%s_%s', $schemeAndHost, $extSystemSlug);

        if (isset(
            $this->domainAllowMap[$key],
            $this->domainAllowMap[$key]['crop_allow_list'],
            $this->domainAllowList[$this->domainAllowMap[$key]['crop_allow_list']])
        ) {
            return CropAllowListConfiguration::getFromArrayConfiguration(
                $this->domainAllowList[$this->domainAllowMap[$key]['crop_allow_list']]
            );
        }

        throw new DomainException("Domain ({$schemeAndHost}) not supported");
    }

    /**
     * @return array<string, CropAllowItem>
     */
    public function getTaggedList(string $allowListName, string $tag): array
    {
        $key = $this->getKey($allowListName, $tag);
        if (false === isset($this->taggedListCache[$key])) {
            $this->buildTagListCache($allowListName, $tag);
        }

        return $this->taggedListCache[$key] ?? [];
    }

    private function buildTagListCache(string $allowListName, string $tag): void
    {
        $cacheRecord = [];
        if (isset($this->taggedAllowList[$allowListName][$tag])) {
            foreach ($this->taggedAllowList[$allowListName][$tag] as $crop) {
                $cacheRecord[] = new CropAllowItem(
                    (int) $crop[self::CROP_ALLOW_ITEM_WIDTH],
                    (int) $crop[self::CROP_ALLOW_ITEM_HEIGHT],
                    (string) $crop[self::CROP_ALLOW_ITEM_TITLE],
                );
            }
        }

        $this->taggedListCache[$this->getKey($allowListName, $tag)] = $cacheRecord;
    }

    private function getKey(string $allowListName, string $tag): string
    {
        return $allowListName . '_' . $tag;
    }

    private function getDomainName(): string
    {
        $schemeAndHost = $this->domainProvider->getSchemeAndHost();

        if (isset($this->domainNames[$schemeAndHost])) {
            return $this->domainNames[$schemeAndHost];
        }

        throw new DomainException("Domain ({$schemeAndHost}) not supported");
    }
}
