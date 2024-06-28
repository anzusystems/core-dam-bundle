<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Cache\Settings;

use AnzuSystems\Contracts\Response\Cache\AbstractCacheSettings;

final class AdmConfigCacheSettings extends AbstractCacheSettings
{
    public const string CACHE_KEY = 'core-dam-adm-config';
    private const int CACHE_TTL = 604_800; // one week

    public function __construct()
    {
        parent::__construct(self::CACHE_TTL);
    }

    protected function getXKeys(): array
    {
        return [self::CACHE_KEY];
    }
}
