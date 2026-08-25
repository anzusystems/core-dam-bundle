<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\FileSystem;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFileOptimalResize;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;

final class FileSystemProviderTest extends CoreDamKernelTestCase
{
    private const string OVERRIDE_STORAGE_NAME = 'blog.image';
    private const string OVERRIDE_CROP_STORAGE_NAME = 'blog.crop';
    private const string DEFAULT_STORAGE_NAME = 'cms.image';

    private FileSystemProvider $fileSystemProvider;
    private AssetLicenceRepository $assetLicenceRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileSystemProvider = $this->getService(FileSystemProvider::class);
        $this->assetLicenceRepository = $this->getService(AssetLicenceRepository::class);
    }

    public function testAssetFileWithOverriddenLicenceUsesOverrideStorageName(): void
    {
        $image = $this->buildImageFile(AssetLicenceFixtures::LICENCE_NO_UPLOAD_ID);

        self::assertSame(self::OVERRIDE_STORAGE_NAME, $this->fileSystemProvider->getStorageNameByStorable($image));
    }

    public function testOptimalResizeOfOverriddenImageUsesOverrideStorageName(): void
    {
        $image = $this->buildImageFile(AssetLicenceFixtures::LICENCE_NO_UPLOAD_ID);
        $resize = (new ImageFileOptimalResize())->setImage($image);

        self::assertSame(self::OVERRIDE_STORAGE_NAME, $this->fileSystemProvider->getStorageNameByStorable($resize));
    }

    public function testAssetFileWithoutOverrideFallsBackToExtSystemConfig(): void
    {
        $image = $this->buildImageFile(BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID);

        self::assertSame(self::DEFAULT_STORAGE_NAME, $this->fileSystemProvider->getStorageNameByStorable($image));
    }

    public function testChunkStorableIgnoresLicenceOverride(): void
    {
        $image = $this->buildImageFile(AssetLicenceFixtures::LICENCE_NO_UPLOAD_ID);
        $chunk = (new Chunk())->setAssetFile($image);

        self::assertSame('cms.chunk', $this->fileSystemProvider->getStorageNameByStorable($chunk));
    }

    public function testCropFilesystemByImageUsesOverrideWhenConfigured(): void
    {
        $image = $this->buildImageFile(AssetLicenceFixtures::LICENCE_NO_UPLOAD_ID);

        self::assertSame(
            $this->fileSystemProvider->getFileSystemByStorageName(self::OVERRIDE_CROP_STORAGE_NAME),
            $this->fileSystemProvider->getCropFilesystemByImage($image)
        );
    }

    public function testCropFilesystemByImageFallsBackToExtSystemCropStorageWhenNoOverride(): void
    {
        $image = $this->buildImageFile(BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID);

        self::assertSame(
            $this->fileSystemProvider->getCropFilesystemByExtSystemSlug('cms'),
            $this->fileSystemProvider->getCropFilesystemByImage($image)
        );
    }

    private function buildImageFile(int $licenceId): ImageFile
    {
        $licence = $this->assetLicenceRepository->find($licenceId);
        self::assertNotNull($licence, "Fixture licence ({$licenceId}) not found");

        $image = new ImageFile();
        $image->setLicence($licence);

        return $image;
    }
}
