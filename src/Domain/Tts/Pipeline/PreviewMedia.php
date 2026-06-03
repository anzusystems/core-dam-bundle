<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileMetadata\AssetFileMetadataManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Entity\AssetFileMetadata;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\TmpLocalFilesystem;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Generates the short-clip preview AudioFile attached to the preview slot of the master's Asset.
 * Master must be persisted with a valid file path (produced by {@see TtsAudioFactory} + materialised
 * via {@see AudioStatusFacade::storeAndProcess()} from {@see TtsRequestOrchestrator}).
 */
final readonly class PreviewMedia
{
    private const int PREVIEW_DURATION_SECONDS = 30;
    private const int PREVIEW_START_SECONDS = 0;
    private const int PREVIEW_PATH_RANDOM_BYTES = 8;
    private const int PREVIEW_ROUTE_RANDOM_BYTES = 16;

    public function __construct(
        private AssetFileRouteFactory $routeFactory,
        private AssetFileRouteFacade $routeFacade,
        private AssetFileMetadataManager $metadataManager,
        private FileSystemProvider $fileSystemProvider,
        private AssetFileManager $audioFileManager,
        private AudioStatusFacade $audioStatusFacade,
        private FfmpegService $ffmpegService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * MUST run outside an open transaction — blocking ffmpeg + storage I/O. Wraps its own short tx
     * for the entity create + publish phase.
     *
     * If $masterTmpFile is provided (typically by {@see TtsAudioFactory} which already mirrored the
     * master into tmp during creation), the remote-storage re-download is skipped.
     *
     * @throws RuntimeException on ffmpeg failure or storage errors
     * @throws FilesystemException on filesystem read/write errors
     * @throws FfmpegException on ffmpeg failure
     */
    public function generate(AudioFile $masterAudioFile, ?AdapterFile $masterTmpFile = null, ?DateTimeImmutable $expireAt = null): AudioFile
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

            /** @var array{0: AudioFile, 1: AssetFileRoute} $created */
            $created = $this->entityManager->wrapInTransaction(
                function () use ($masterAudioFile, $previewFile, $expireAt): array {
                    $preview = $this->createPreviewAudioFile($masterAudioFile, $previewFile);
                    // Regen: stamp the same safety expireAt as the master so a pre-swap crash leaves the unslotted
                    // preview reapable; AssetSwap clears it on promote. Initial passes null (preview is slotted directly).
                    $preview->setExpireAt($expireAt);
                    $route = $this->createRouteForPreview($preview);
                    $this->entityManager->flush();

                    return [$preview, $route];
                }
            );

            [$preview, $route] = $created;
            $this->processPreview($preview, $previewFile);
            $this->routeFacade->makePublic($preview, $route);

            return $preview;
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
     * Preview lives at a TTS-specific {@see Config::PREVIEW_STORAGE_PREFIX} path (not the date-based
     * layout {@see \AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\AssetFileStorageOperator}
     * produces). The bytes are written here so the status-facade's process phase sees a `Stored` file
     * and skips its own `store()` write.
     *
     * @throws FilesystemException
     */
    private function createPreviewAudioFile(AudioFile $master, AdapterFile $previewFile): AudioFile
    {
        $metadata = new AssetFileMetadata();
        $this->metadataManager->create($metadata, false);

        $preview = new AudioFile();
        $preview->setMetadata($metadata);
        $preview->setLicence($master->getLicence());
        $preview->setAsset($master->getAsset());
        $preview->getAssetAttributes()
            ->setMimeType(AudioMimeTypes::MimeMpeg->value)
            ->setSize($previewFile->getSize())
            ->setStatus(AssetFileProcessStatus::Stored)
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

        return $this->routeFactory->createPrebuiltAudioRoute($previewAudio, $slug, $path);
    }

    /**
     * Run the standard process phase (audio attributes via ffprobe, metadata, Stored → Processed transition,
     * AssetFileChangedEvent dispatch, AssetRefreshProperties message). Bytes are already in storage so
     * the facade's `store()` write is skipped.
     */
    private function processPreview(AudioFile $preview, AdapterFile $previewFile): void
    {
        $this->audioStatusFacade->storeAndProcess(
            assetFile: $preview,
            file: $previewFile,
            dispatchPropertyRefresh: false,
        );

        if ($preview->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            throw new RuntimeException(sprintf(
                'TTS preview audio (%s) finished storeAndProcess in status "%s" instead of "%s".',
                (string) $preview->getId(),
                $preview->getAssetAttributes()->getStatus()->value,
                AssetFileProcessStatus::Processed->value,
            ));
        }
    }
}
