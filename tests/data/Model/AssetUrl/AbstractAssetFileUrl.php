<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;


abstract class AbstractAssetFileUrl implements AssetUrlInterface
{
    public function __construct(
        protected int $licenceId,
        protected int $version = 1,
    ) {
    }
}
