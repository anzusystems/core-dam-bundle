<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\FileSystemStorableInterface;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use AnzuSystems\CoreDamBundle\FileSystem\Adapter\LocalFileSystemAdapter;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetFileRoutePublicStorageInterface;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemImageTypeConfiguration;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Doctrine\Common\Util\ClassUtils;

final class FileSystemProvider
{
    public const string TMP_STORAGE_SETTINGS = 'tmp_dir_path';
    public const string FIXTURES_STORAGE_SETTINGS = 'fixtures_dir_path';

    private ?LocalFilesystem $tmpFilesystem = null;
    private ?LocalFilesystem $fixturesFileSystem = null;

    public function __construct(
        private readonly array $fileOperations,
        private readonly NameGenerator $nameGenerator,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly StorageProviderContainer $storageProviderContainer,
    ) {
    }

    public function createLocalFilesystem(string $path): LocalFilesystem
    {
        return new LocalFilesystem(
            adapter: new LocalFileSystemAdapter($path),
            directory: $path,
        );
    }

    public function getTmpFileSystem(): TmpLocalFilesystem
    {
        if (null === $this->tmpFilesystem) {
            $path = $this->fileOperations[self::TMP_STORAGE_SETTINGS];
            $this->tmpFilesystem = (new TmpLocalFilesystem(
                adapter: new LocalFileSystemAdapter($path),
                directory: $path,
            ))->setNameGenerator($this->nameGenerator);
        }

        return $this->tmpFilesystem;
    }

    public function getFixturesFileSystem(): LocalFilesystem
    {
        if (null === $this->fixturesFileSystem) {
            $path = $this->fileOperations[self::FIXTURES_STORAGE_SETTINGS];
            $this->fixturesFileSystem = $this->createLocalFilesystem($path);
        }

        return $this->fixturesFileSystem;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getCropFilesystemByExtSystemSlug(string $extSystem): AbstractFilesystem
    {
        /** @var ExtSystemImageTypeConfiguration $extSystemConfig */
        $extSystemConfig = $this->extSystemConfigurationProvider->getAssetConfiguration(
            $extSystem,
            AssetType::Image,
        );

        $filesystem = $this->getFileSystemByStorageName($extSystemConfig->getCropStorageName());

        if (null === $filesystem) {
            throw new InvalidArgumentException("Unknown storage name ({$extSystemConfig->getStorageName()})");
        }

        return $filesystem;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getPublicFilesystem(AssetFile $assetFile): AbstractFilesystem
    {
        $extSystemConfig = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAsset($assetFile->getAsset());
        if (false === ($extSystemConfig instanceof AssetFileRoutePublicStorageInterface)) {
            throw new InvalidArgumentException('Unsupported public storage');
        }
        $filesystem = $this->getFileSystemByStorageName($extSystemConfig->getPublicStorage());

        if (null === $filesystem) {
            throw new InvalidArgumentException('Undefined public storage');
        }

        return $filesystem;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getFilesystemByStorable(FileSystemStorableInterface $storable): AbstractFilesystem
    {
        $storage = $this->getStorageNameByStorable($storable);
        $filesystem = $this->getFileSystemByStorageName($storage);

        if (null === $filesystem) {
            throw new InvalidArgumentException("Unknown storage name ({$storage})");
        }

        return $filesystem;
    }

    public function getStorageNameByStorable(FileSystemStorableInterface $storable): string
    {
        $extSystemConfig = $this->extSystemConfigurationProvider->getAssetConfiguration(
            $storable->getExtSystem()->getSlug(),
            $storable->getAssetType(),
        );

        if (AssetFileRoute::class === ClassUtils::getRealClass($storable::class)) {
            if ($extSystemConfig instanceof AssetFileRoutePublicStorageInterface) {
                return $extSystemConfig->getPublicStorage();
            }

            throw new InvalidArgumentException(
                "Route with asset type ({$storable->getAssetType()->toString()}) does not support storage"
            );
        }

        if (Chunk::class === ClassUtils::getRealClass($storable::class)) {
            return $extSystemConfig->getChunkStorageName();
        }

        return $extSystemConfig->getStorageName();
    }

    public function getFileSystemByStorageName(string $storageName): ?AbstractFilesystem
    {
        if ($this->storageProviderContainer->has($storageName)) {
            return $this->storageProviderContainer->get($storageName);
        }

        return null;
    }
}
