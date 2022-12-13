<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractPublicController extends BaseController
{
    private const CACHE_CONTROL_TTL_HEADER = 'X-Cache-Control-TTL';
    private const X_KEY_HEADER = 'xkey';

    private AllowListConfiguration $allowListConfiguration;

    #[Required]
    public function setAllowListConfiguration(AllowListConfiguration $allowListConfiguration): void
    {
        $this->allowListConfiguration = $allowListConfiguration;
    }

    protected function setCache(Response $response, AssetFile $asset): void
    {
        $cache = $this->allowListConfiguration->getCacheConfiguration();
        if ($cache->isPublic()) {
            $response->setPublic();
        }
        $response->setMaxAge($cache->getMaxAge());
        $response->headers->set(self::CACHE_CONTROL_TTL_HEADER, (string) $cache->getCacheTtl());
        $this->setXKeys($response, $asset);
    }

    private function setXKeys(Response $response, AssetFile $asset): void
    {
        $response->headers->set(self::X_KEY_HEADER, implode(' ', [
            self::getSystemXkey(),
            self::getSystemXkey() . '-' . $asset->getAsset()->getAsset()->getAttributes()->getAssetType()->toString(),
            $asset->getId(),
        ]));
    }

    private function getSystemXKey(): string
    {
        return 'anzu-' . AnzuApp::getAppSystem();
    }
}
