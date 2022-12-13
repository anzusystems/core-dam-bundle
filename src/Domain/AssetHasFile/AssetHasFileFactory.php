<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetHasFile;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetHasFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeConfiguration;

class AssetHasFileFactory
{
    public function __construct(
        private readonly AssetHasFileManager $manager,
        private readonly ExtSystemConfigurationProvider $configurationProvider,
    ) {
    }

    public function createRelation(Asset $asset, AssetFile $assetFile, ?string $version = null, bool $flush = true): AssetHasFile
    {
        $assetHasFile = $this->initRelationEntity($asset, $version);
        $assetHasFile->setAsset($asset);
        $asset->getFiles()->add($assetHasFile);

        $assetFile->setAsset($assetHasFile);

        match ($assetFile::class) {
            ImageFile::class => $assetHasFile->setImage($assetFile),
            AudioFile::class => $assetHasFile->setAudio($assetFile),
            DocumentFile::class => $assetHasFile->setDocument($assetFile),
            VideoFile::class => $assetHasFile->setVideo($assetFile),
        };

        return $this->manager->create($assetHasFile, $flush);
    }

    private function initRelationEntity(Asset $asset, ?string $version = null): AssetHasFile
    {
        $configuration = $this->configurationProvider->getExtSystemConfigurationByAsset($asset);
        $assetHasFile = new AssetHasFile();
        $versionString = $this->getVersionString($configuration, $version);
        $assetHasFile->setVersionTitle($versionString);
        $assetHasFile->setDefault($configuration->getFileVersions()->getDefault() === $versionString);

        return $assetHasFile;
    }

    private function getVersionString(
        ExtSystemAssetTypeConfiguration $configuration,
        ?string $version = null
    ): string {
        if (null === $version) {
            return $configuration->getFileVersions()->getDefault();
        }

        if (in_array($version, $configuration->getFileVersions()->getVersions(), true)) {
            return $version;
        }

        throw new DomainException('invalid_position');
    }
}
