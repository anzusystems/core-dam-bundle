<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\AssetFileMetadata\AssetFileMetadataManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileMetadata;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\AssetExternalProviderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\DocumentMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\VideoMimeTypes;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginExternalProvider;
use Doctrine\ORM\NonUniqueResultException;

abstract class AssetFileFactory
{
    public function __construct(
        private readonly AssetFactory $assetFactory,
        private readonly AssetManager $assetManager,
        private readonly AssetFileManager $assetFileManager,
        private readonly AssetFileMetadataManager $assetFileMetadataManager,
        private readonly AssetTextsWriter $textsWriter,
        private readonly ExtSystemConfigurationProvider $configurationProvider,
    ) {
    }

    /**
     * @throws DomainException
     */
    public function createFromFile(File $file, AssetLicence $assetLicence, ?string $id = null): AssetFile
    {
        $assetFile = $this->createBlankAssetFile($file, $assetLicence, $id);
        $this->assetFactory->createForAssetFile($assetFile, $assetLicence);

        return $assetFile;
    }

    public function createBlankAssetFile(File $file, AssetLicence $licence, ?string $id = null): AssetFile
    {
        $assetFile = null;
        if (in_array($file->getMimeType(), ImageMimeTypes::values(), true)) {
            $assetFile = $this->createBlankImage($licence, $id);
        }
        if (in_array($file->getMimeType(), AudioMimeTypes::values(), true)) {
            $assetFile = $this->createBlankAudio($licence, $id);
        }
        if (in_array($file->getMimeType(), DocumentMimeTypes::values(), true)) {
            $assetFile = $this->createBlankDocument($licence, $id);
        }
        if (in_array($file->getMimeType(), VideoMimeTypes::values(), true)) {
            $assetFile = $this->createBlankVideo($licence, $id);
        }

        if (null === $assetFile) {
            throw new DomainException(sprintf('File with mime type (%s) cannot be created', $file->getMimeType()));
        }

        return $this->assetFileManager->create($assetFile, false);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function createFromExternalProvider(
        string $providerName,
        AssetExternalProviderDto $assetDto,
        AssetLicence $assetLicence,
    ): AssetFile {
        $assetFile = $this->createAssetFileForExternalProvider($providerName, $assetDto, $assetLicence);
        $asset = $this->assetFactory->createForAssetFile($assetFile, $assetLicence);
        $this->textsWriter->writeValues(
            from: $assetDto,
            to: $asset,
            config: $this->configurationProvider->getExtSystemConfigurationByAsset($asset)->getAssetExternalProvidersMap()
        );
        $this->assetManager->updateExisting($asset, false);

        return $this->assetFileManager->create($assetFile, false);
    }

    protected function createBlankImage(AssetLicence $licence, ?string $id = null): ImageFile
    {
        $metadata = new AssetFileMetadata();
        $this->assetFileMetadataManager->create($metadata, false);

        return (new ImageFile())
            ->setId($id)
            ->setMetadata($metadata)
            ->setLicence($licence)
        ;
    }

    protected function createBlankAudio(AssetLicence $licence, ?string $id = null): AudioFile
    {
        $metadata = new AssetFileMetadata();
        $this->assetFileMetadataManager->create($metadata, false);

        return (new AudioFile())
            ->setId($id)
            ->setMetadata($metadata)
            ->setLicence($licence)
        ;
    }

    protected function createBlankVideo(AssetLicence $licence, ?string $id = null): VideoFile
    {
        $metadata = new AssetFileMetadata();
        $this->assetFileMetadataManager->create($metadata, false);

        return (new VideoFile())
            ->setId($id)
            ->setMetadata($metadata)
            ->setLicence($licence)
        ;
    }

    protected function createBlankDocument(AssetLicence $licence, ?string $id = null): DocumentFile
    {
        $metadata = new AssetFileMetadata();
        $this->assetFileMetadataManager->create($metadata, false);

        return (new DocumentFile())
            ->setId($id)
            ->setMetadata($metadata)
            ->setLicence($licence)
        ;
    }

    abstract protected function createFromAdmDto(AssetLicence $licence, AssetFileAdmCreateDto $createDto): AssetFile;

    private function createAssetFileForExternalProvider(
        string $providerName,
        AssetExternalProviderDto $assetDto,
        AssetLicence $licence,
    ): AssetFile {
        $assetType = $assetDto->getAttributes()->getAssetType();
        $assetFile = match ($assetType) {
            AssetType::Image => $this->createBlankImage($licence),
            AssetType::Audio => $this->createBlankAudio($licence),
            AssetType::Document => $this->createBlankDocument($licence),
            AssetType::Video => $this->createBlankVideo($licence),
            default => throw new DomainException(sprintf('File with cannot be created for type (%s)', $assetType->toString())),
        };

        $assetFile->getAssetAttributes()
            ->setStatus(AssetFileProcessStatus::Uploaded)
            ->setCreateStrategy(AssetFileCreateStrategy::ExternalProvider)
            ->setOriginUrl($assetDto->getUrl())
            ->setOriginExternalProvider(new OriginExternalProvider($providerName, $assetDto->getId()))
        ;

        return $assetFile;
    }
}
