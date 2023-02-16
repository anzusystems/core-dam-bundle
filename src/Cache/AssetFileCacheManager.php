<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Cache;

use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

final class AssetFileCacheManager
{
    private const CACHE_CONTROL_TTL_HEADER = 'X-Cache-Control-TTL';
    private const X_KEY_HEADER = 'xkey';

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
        $response->setMaxAge($cache->getMaxAge());
        $response->headers->set(self::CACHE_CONTROL_TTL_HEADER, (string) $cache->getCacheTtl());
        $this->setXKeys($response, $asset);
    }

    public static function getAssetFileXKey(string $assetId): string
    {
        return $assetId;
    }

    public static function getAssetTypeXKey(AssetFile $asset): string
    {
        return self::getSystemXkey() . '-' . $asset->getAsset()->getAttributes()->getAssetType()->toString();
    }

    private function setXKeys(Response $response, AssetFile $asset): void
    {
        $response->headers->set(self::X_KEY_HEADER, implode(' ', [
            self::getSystemXkey(),
            self::getAssetTypeXKey($asset),
            self::getAssetFileXKey((string) $asset->getId()),
        ]));
    }

    private static function getSystemXKey(): string
    {
        return 'anzu-' . AnzuApp::getAppSystem();
    }
}
