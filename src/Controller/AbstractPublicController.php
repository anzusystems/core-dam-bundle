<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\CoreDamBundle\Cache\AssetFileCacheManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractPublicController extends BaseController
{
    private AssetFileCacheManager $assetFileCacheManager;

    #[Required]
    public function setAssetFileCacheManager(AssetFileCacheManager $assetFileCacheManager): void
    {
        $this->assetFileCacheManager = $assetFileCacheManager;
    }

    protected function setCache(Response $response, AssetFile $asset): void
    {
        $this->assetFileCacheManager->setCache($response, $asset);
    }
}
