<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDistributionFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\AbstractAssetFileMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\AudioFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\DocumentFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\ImageFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\MediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use League\Flysystem\FilesystemException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

final class AssetFileChangeStateHandler
{
    use MessageBusAwareTrait;

    public function __construct(
        private readonly AssetFileRepository $assetFileRepository,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
        private readonly DamLogger $damLogger,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly VideoDistributionFacade $videoDistributionFacade,
        private readonly AssetRepository $assetRepository,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws RuntimeException
     */
    #[AsMessageHandler]
    public function handleVideoFile(VideoFileChangeStateMessage $message): void
    {
        $assetFile = $this->handleAssetFile($message);
        if (false === ($assetFile instanceof VideoFile)) {
            return;
        }

        $services = $message->getDistributionServices();
        $status = $assetFile->getAssetAttributes()->getStatus();
        if ($status->is(AssetFileProcessStatus::Processed)) {
            $this->ensureDistributed($assetFile, $services);

            return;
        }

        if ($status->is(AssetFileProcessStatus::Failed)) {
            $this->handleFailed($assetFile, $services);

            return;
        }

        if ($status->is(AssetFileProcessStatus::Duplicate)) {
            $this->handleDuplicate($assetFile, $services);
        }
    }

    /**
     * @throws SerializerException
     * @throws RuntimeException
     */
    #[AsMessageHandler]
    public function handleAudioFile(AudioFileChangeStateMessage $message): void
    {
        $this->handleAssetFile($message);
    }

    /**
     * @throws SerializerException
     * @throws RuntimeException
     */
    #[AsMessageHandler]
    public function handleImageFile(ImageFileChangeStateMessage $message): void
    {
        $this->handleAssetFile($message);
    }

    /**
     * @throws SerializerException
     * @throws RuntimeException
     */
    #[AsMessageHandler]
    public function handleDocumentFile(DocumentFileChangeStateMessage $message): void
    {
        $this->handleAssetFile($message);
    }

    /**
     * @param list<string> $services
     */
    private function handleFailed(VideoFile $videoFile, array $services): void
    {
        if ($this->isCreatedByDownload($videoFile)) {
            $this->messageBus->dispatch(new MediaStatusCallbackMessage(
                extSystemId: $videoFile->getExtSystem()->getId(),
                assetId: (string) $videoFile->getAsset()->getId(),
                status: MediaStatusType::VideoFailed,
                failureReason: $videoFile->getAssetAttributes()->getFailReason()->toString(),
            ));
        }

        if ([] !== $services) {
            $this->logDistributionNotStarted($videoFile, $services);
        }
    }

    /**
     * @param list<string> $services
     *
     * @throws RuntimeException
     */
    private function handleDuplicate(VideoFile $videoFile, array $services): void
    {
        $originMainFile = $this->isCreatedByDownload($videoFile)
            ? $this->assetRepository->find($videoFile->getAssetAttributes()->getOriginAssetId())?->getMainFile()
            : null;

        $originProcessed = $originMainFile instanceof VideoFile
            && $originMainFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed);

        if ($originProcessed) {
            $this->ensureDistributed($originMainFile, $services);
        }
        if (false === $originProcessed && [] !== $services) {
            $this->logDistributionNotStarted($videoFile, $services);
        }

        if ($this->isCreatedByDownload($videoFile)) {
            $this->messageBus->dispatch(new MediaStatusCallbackMessage(
                extSystemId: $videoFile->getExtSystem()->getId(),
                assetId: (string) $videoFile->getAsset()->getId(),
                status: MediaStatusType::VideoDuplicate,
                originAssetId: $videoFile->getAssetAttributes()->getOriginAssetId(),
            ));
        }
    }

    private function isCreatedByDownload(VideoFile $videoFile): bool
    {
        return $videoFile->getAssetAttributes()->getCreateStrategy()->is(AssetFileCreateStrategy::Download);
    }

    /**
     * @param list<string> $services
     */
    private function logDistributionNotStarted(VideoFile $videoFile, array $services): void
    {
        $this->damLogger->error(
            DamLogger::NAMESPACE_ASSET_FILE_CHANGE_STATE,
            sprintf(
                'VideoFile (%s) in status (%s), requested distributions (%s) not started',
                (string) $videoFile->getId(),
                $videoFile->getAssetAttributes()->getStatus()->toString(),
                implode(', ', $services)
            )
        );
    }

    /**
     * @param list<string> $services
     *
     * @throws RuntimeException
     */
    private function ensureDistributed(VideoFile $videoFile, array $services): void
    {
        try {
            $this->videoDistributionFacade->ensureDistributed($videoFile, $services);
        } catch (Throwable $e) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_FILE_CHANGE_STATE,
                sprintf('VideoFile (%s) distribution start failed', (string) $videoFile->getId()),
                exception: $e
            );

            throw new RuntimeException(message: $e->getMessage(), previous: $e);
        }
    }

    /**
     * @throws FilesystemException
     * @throws SerializerException
     */
    private function handleAssetFile(AbstractAssetFileMessage $message): ?AssetFile
    {
        $assetFile = $this->assetFileRepository->find($message->getAssetId());

        if (null === $assetFile) {
            return null;
        }

        try {
            match ($assetFile->getAssetAttributes()->getStatus()) {
                AssetFileProcessStatus::Uploaded => $this->facadeProvider->getStatusFacade($assetFile)->storeAndProcess($assetFile),
                AssetFileProcessStatus::Stored => $this->handleStored($assetFile),

                default => $this->damLogger->info(
                    DamLogger::NAMESPACE_ASSET_CHANGE_STATE,
                    sprintf(
                        'AssetFile (%s) change state to (%s) not suitable for handle',
                        (string) $assetFile->getId(),
                        $assetFile->getAssetAttributes()->getStatus()->toString()
                    ),
                )
            };
        } catch (Throwable $e) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_FILE_CHANGE_STATE,
                sprintf(
                    'AssetFile (%s) change state to (%s) failed',
                    (string) $assetFile->getId(),
                    $assetFile->getAssetAttributes()->getStatus()->toString()
                ),
                exception: $e
            );
            $this->fileSystemProvider->getTmpFileSystem()->clearPaths();

            throw new RuntimeException(message: $e->getMessage(), previous: $e);
        }

        $this->fileSystemProvider->getTmpFileSystem()->clearPaths();

        return $assetFile;
    }

    /**
     * @throws FilesystemException
     * @throws SerializerException
     */
    private function handleStored(AssetFile $assetFile): AssetFile
    {
        $this->damLogger->warning(
            DamLogger::NAMESPACE_ASSET_FILE_CHANGE_STATE,
            sprintf(
                'AssetFile (%s) handling storing state (possible killed by k8s).',
                (string) $assetFile->getId(),
            ),
        );
        $tmpFilesystem = $this->fileSystemProvider->getTmpFileSystem();

        return $this->facadeProvider
            ->getStatusFacade($assetFile)
            ->storeAndProcess(
                assetFile: $assetFile,
                file: AdapterFile::createFromBaseFile(
                    file: $tmpFilesystem->writeTmpFileFromFilesystem(
                        $this->fileSystemProvider->getFilesystemByStorable($assetFile),
                        $assetFile->getFilePath()
                    ),
                    filesystem: $tmpFilesystem
                )
            );
    }
}
