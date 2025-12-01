<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Cache;

use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

final class AssetFileCacheManager
{
    public const string CACHE_CONTROL_TTL_HEADER = 'X-Cache-Control-TTL';
    public const string X_KEY_HEADER = 'xkey';
    public const string CACHE_TAG_HEADER = 'Cache-tag';
    private const int NOT_FOUND_TTL = 360;

    private AllowListConfiguration $allowListConfiguration;

    #[Required]
    public function setAllowListConfiguration(AllowListConfiguration $allowListConfiguration): void
    {
        $this->allowListConfiguration = $allowListConfiguration;
    }

    public function setCache(Response $response, AssetFile $asset): void
    {
        $cache = $this->allowListConfiguration->getCacheConfiguration();
        if ($cache->isPublic()) {
            $response->setPublic();
        }

        if ($cache->isMusRevalidate()) {
            $response->headers->addCacheControlDirective('must-revalidate');
        }
        $response->setMaxAge($cache->getMaxAge());
        $response->headers->set(self::CACHE_CONTROL_TTL_HEADER, (string) $cache->getCacheTtl());
        $this->setXKeys($response, $asset);
        $this->setCachedTag($response, $asset);
    }

    public function setNotFoundCache(Response $response): void
    {
        $response->setPublic();
        $response->setMaxAge(self::NOT_FOUND_TTL);
        $response->headers->set(self::CACHE_CONTROL_TTL_HEADER, (string) self::NOT_FOUND_TTL);
    }

    public static function getAssetFileXKey(string $assetId): string
    {
        return $assetId;
    }

    public static function getAssetFileXKeyPrefixed(AssetType $assetType, string $assetId): string
    {
        return self::getAssetTypeXKey($assetType) . '-' . self::getAssetFileXKey($assetId);
    }

    public static function getAssetTypeXKey(AssetType $assetType): string
    {
        return self::getSystemXkey() . '-' . $assetType->toString();
    }

    public static function getSystemXKey(): string
    {
        return 'anzu-' . AnzuApp::getAppSystem();
    }

    private function setXKeys(Response $response, AssetFile $asset): void
    {
        $response->headers->set(self::X_KEY_HEADER, $this->getCacheTags($asset));
    }

    private function setCachedTag(Response $response, AssetFile $asset): void
    {
        $response->headers->set(self::CACHE_TAG_HEADER, $this->getCacheTags($asset));
    }

    private function getCacheTags(AssetFile $asset): string
    {
        $assetType = $asset->getAsset()->getAttributes()->getAssetType();
        $assetId = (string) $asset->getId();

        return implode(',', [
            self::getSystemXkey(),
            self::getAssetTypeXKey($assetType),
            self::getAssetFileXKey($asset->getId()),
            self::getAssetFileXKeyPrefixed($assetType, $assetId),
        ]);
    }
}
