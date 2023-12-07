<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

interface AssetFileRoutePublicStorageInterface
{
    public function getPublicStorage(): string;
}
