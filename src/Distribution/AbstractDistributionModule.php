<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\CustomDistribution;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractDistributionModule implements DistributionModuleInterface
{
    protected AssetFileRepository $assetFileRepository;
    protected FileSystemProvider $fileSystemProvider;
    protected DistributionConfigurationProvider $distributionConfigurationProvider;

    #[Required]
    public function setDistributionConfigurationProvider(DistributionConfigurationProvider $distributionConfigurationProvider): void
    {
        $this->distributionConfigurationProvider = $distributionConfigurationProvider;
    }

    #[Required]
    public function setAssetFileRepository(AssetFileRepository $assetFileRepository): void
    {
        $this->assetFileRepository = $assetFileRepository;
    }

    #[Required]
    public function setFileSystemProvider(FileSystemProvider $fileSystemProvider): void
    {
        $this->fileSystemProvider = $fileSystemProvider;
    }

    public function isAuthenticated(string $distributionService): bool
    {
        return true;
    }

    public static function getDefaultKeyName(): string
    {
        return static::class;
    }

    public static function supportsDistributionResourceName(): string
    {
        return CustomDistribution::getResourceName();
    }

    /**
     * @throws FilesystemException
     */
    protected function getLocalFileCopy(AssetFile $assetFile): File
    {
        $tmpFilesystem = $this->fileSystemProvider->getTmpFileSystem();

        return $tmpFilesystem->writeTmpFileFromFilesystem(
            $this->fileSystemProvider->getFilesystemByStorable($assetFile),
            $assetFile->getFilePath()
        );
    }
}
