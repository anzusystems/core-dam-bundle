<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\App;

class AssetLicenceStorageOverrideConfiguration
{
    public const string STORAGE_NAME_KEY = 'storage_name';
    public const string CROP_STORAGE_NAME_KEY = 'crop_storage_name';

    public function __construct(
        private readonly string $storageName,
        private readonly string $cropStorageName,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): static
    {
        return new static(
            storageName: (string) ($config[self::STORAGE_NAME_KEY] ?? App::EMPTY_STRING),
            cropStorageName: (string) ($config[self::CROP_STORAGE_NAME_KEY] ?? App::EMPTY_STRING),
        );
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getCropStorageName(): string
    {
        return $this->cropStorageName;
    }
}
