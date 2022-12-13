<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\GCloudFilesystem;
use AnzuSystems\CoreDamBundle\FileSystem\LocalFilesystem;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileDownloadAdmGetDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use DateTime;
use Symfony\Component\Routing\RouterInterface;

final class AssetFileDownloadFacade
{
    private const G_CLOUD_DOWNLOAD_LINK_MODIFIER = '15 min';

    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly RouterInterface $router,
        private readonly ConfigurationProvider $configurationProvider,
    ) {
    }

    public function decorateDownloadLink(AssetFile $assetFile): AssetFileDownloadAdmGetDto
    {
        return AssetFileDownloadAdmGetDto::getInstance(
            $assetFile,
            $this->getDownloadLink($assetFile)
        );
    }

    public function getDownloadLink(AssetFile $assetFile): string
    {
        $filesystem = $this->fileSystemProvider->getFilesystemByStorable($assetFile);
        if ($filesystem instanceof GCloudFilesystem) {
            return $filesystem->getDownloadLink($assetFile, new DateTime(self::G_CLOUD_DOWNLOAD_LINK_MODIFIER));
        }
        if ($filesystem instanceof LocalFilesystem) {
            return $this->configurationProvider->getSettings()->getApiDomainKey() .
                match ($assetFile->getAssetType()) {
                    AssetType::Image => $this->router->generate('adm_image_download', ['image' => $assetFile->getId()]),
                    AssetType::Video => $this->router->generate('adm_video_download', ['video' => $assetFile->getId()]),
                    AssetType::Audio => $this->router->generate('adm_audio_download', ['audio' => $assetFile->getId()]),
                    AssetType::Document => $this->router->generate('adm_document_download', ['document' => $assetFile->getId()]),
                };
        }

        return '';
    }
}
