<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Configuration;

use AnzuSystems\CoreDamBundle\Domain\Configuration\AssetLicenceStorageOverrideProvider;
use PHPUnit\Framework\TestCase;

final class AssetLicenceStorageOverrideProviderTest extends TestCase
{
    private const int KNOWN_LICENCE_ID = 100_100;
    private const int UNKNOWN_LICENCE_ID = 999_999;

    private AssetLicenceStorageOverrideProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new AssetLicenceStorageOverrideProvider([
            self::KNOWN_LICENCE_ID => [
                'storage_name' => 'agency.image',
                'crop_storage_name' => 'agency.crop',
            ],
        ]);
    }

    public function testKnownLicenceReturnsConfiguredStorageNames(): void
    {
        self::assertSame('agency.image', $this->provider->getStorageName(self::KNOWN_LICENCE_ID));
        self::assertSame('agency.crop', $this->provider->getCropStorageName(self::KNOWN_LICENCE_ID));
    }

    public function testUnknownLicenceReturnsNull(): void
    {
        self::assertNull($this->provider->getStorageName(self::UNKNOWN_LICENCE_ID));
        self::assertNull($this->provider->getCropStorageName(self::UNKNOWN_LICENCE_ID));
    }
}
