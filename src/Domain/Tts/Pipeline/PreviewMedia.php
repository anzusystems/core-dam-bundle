<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileMetadata\AssetFileMetadataManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteManager;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Entity\AssetFileMetadata;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\RouteUri;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\TmpLocalFilesystem;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteMode;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteStatus;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Generates the short-clip preview AudioFile attached to the preview slot of the master's Asset.
 * Master must be persisted with a valid file path (produced by {@see \AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory::createFromTts()}).
 */
final readonly class PreviewMedia
{
    private const int PREVIEW_DURATION_SECONDS = 30;
    private const int PREVIEW_START_SECONDS = 0;
    private const int PREVIEW_PATH_RANDOM_BYTES = 8;
    private const int PREVIEW_ROUTE_RANDOM_BYTES = 16;

    public function __construct(
        private AssetSlotFactory $assetSlotFactory,
        private AssetFileRouteManager $routeManager,
        private AssetFileMetadataManager $metadataManager,
        private FileSystemProvider $fileSystemProvider,
        private AssetFileManager $audioFileManager,
        private FfmpegService $ffmpegService,
        private Config $config,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * MUST run outside an open transaction — blocking ffmpeg + storage I/O. Wraps its own short tx.
     *
     * If $masterTmpFile is provided (typically by {@see TtsAudioFactory} which already mirrored the
     * master into tmp during creation), the remote-storage re-download is skipped.
     *
     * @throws RuntimeException on ffmpeg failure or storage errors
     * @throws FilesystemException on filesystem read/write errors
     * @throws FfmpegException on ffmpeg failure
     */
    public function generate(AudioFile $masterAudioFile, ?AdapterFile $masterTmpFile = null): AudioFile
    {
        $tmpFs = $this->fileSystemProvider->getTmpFileSystem();
        $masterLocalFile = null !== $masterTmpFile
            ? new File($masterTmpFile->getLocalFilesystem()->extendPath($masterTmpFile->getAdapterPath()))
            : $this->downloadMasterToTmp($masterAudioFile, $tmpFs);

        try {
            $previewFile = $this->ffmpegService->clipAudio(
                $masterLocalFile,
                self::PREVIEW_START_SECONDS,
                self::PREVIEW_DURATION_SECONDS,
            );

            return $this->entityManager->wrapInTransaction(
                function () use ($masterAudioFile, $previewFile): AudioFile {
                    $preview = $this->createPreviewAudioFile($masterAudioFile, $previewFile);

                    $this->assetSlotFactory->createRelation(
                        asset: $masterAudioFile->getAsset(),
                        assetFile: $preview,
                        slotName: $this->config->getPreviewSlotName(),
                        flush: false,
                    );

                    $this->createRouteForPreview($preview);
                    $this->entityManager->flush();

                    return $preview;
                }
            );
        } finally {
            $tmpFs->clearPaths();
        }
    }

    /**
     * @throws FilesystemException
     */
    private function downloadMasterToTmp(AudioFile $masterAudioFile, TmpLocalFilesystem $tmpFs): File
    {
        $masterFs = $this->fileSystemProvider->getFilesystemByStorable($masterAudioFile);
        $rel = $tmpFs->writeTmpFile($masterFs->readStream($masterAudioFile->getAssetAttributes()->getFilePath()));

        return new File($tmpFs->extendPath($rel));
    }

    /**
     * @throws FilesystemException
     */
    private function createPreviewAudioFile(AudioFile $master, AdapterFile $previewFile): AudioFile
    {
        $metadata = new AssetFileMetadata();
        $this->metadataManager->create($metadata, false);

        $preview = new AudioFile();
        $preview->setMetadata($metadata);
        $preview->setLicence($master->getLicence());
        $preview->getAssetAttributes()
            ->setMimeType(AudioMimeTypes::MimeMpeg->value)
            ->setSize($previewFile->getSize())
        ;

        $this->audioFileManager->create($preview, false);

        $fs = $this->fileSystemProvider->getFilesystemByStorable($master);
        $previewRelPath = Config::PREVIEW_STORAGE_PREFIX . StringHelper::base64UrlRandom(self::PREVIEW_PATH_RANDOM_BYTES) . '.' . FfmpegService::AUDIO_EXTENSION_MP3;
        $fs->writeStream($previewRelPath, $previewFile->getLocalFilesystem()->readStream($previewFile->getAdapterPath()));

        $preview->getAssetAttributes()->setFilePath($previewRelPath);

        return $preview;
    }

    private function createRouteForPreview(AudioFile $previewAudio): AssetFileRoute
    {
        $slug = StringHelper::base64UrlRandom(self::PREVIEW_ROUTE_RANDOM_BYTES);
        $path = (string) $previewAudio->getId() . '/' . $slug . '.' . FfmpegService::AUDIO_EXTENSION_MP3;

        $route = (new AssetFileRoute())
            ->setUri(
                (new RouteUri())
                    ->setSlug($slug)
                    ->setMain(true)
                    ->setPath($path)
            )
            ->setStatus(RouteStatus::Active)
            ->setMode(RouteMode::StorageCopy)
        ;
        $route->setTargetAssetFile($previewAudio);
        $previewAudio->getRoutes()->add($route);
        $previewAudio->setMainRoute($route);

        $this->routeManager->create($route, false);

        return $route;
    }
}
