<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\CoreDamBundle\Cache\AssetFileCacheManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseController;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractPublicController extends BaseController
{
    protected AssetFileCacheManager $assetFileCacheManager;

    #[Required]
    public function setAssetFileCacheManager(AssetFileCacheManager $assetFileCacheManager): void
    {
        $this->assetFileCacheManager = $assetFileCacheManager;
    }
}
