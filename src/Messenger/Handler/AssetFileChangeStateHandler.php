<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDistributionStarter;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\AbstractAssetFileMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\AudioFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\DocumentFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\ImageFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use League\Flysystem\FilesystemException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

final class AssetFileChangeStateHandler
{
    public function __construct(
        private readonly AssetFileRepository $assetFileRepository,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
        private readonly DamLogger $damLogger,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly VideoDistributionStarter $videoDistributionStarter,
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
        $services = $message->getDistributionServices();
        if ([] === $services || false === ($assetFile instanceof VideoFile)) {
            return;
        }

        $status = $assetFile->getAssetAttributes()->getStatus();
        if ($status->is(AssetFileProcessStatus::Processed)) {
            try {
                $this->videoDistributionStarter->start($assetFile, $services);
            } catch (Throwable $e) {
                $this->damLogger->error(
                    DamLogger::NAMESPACE_ASSET_FILE_CHANGE_STATE,
                    sprintf('VideoFile (%s) distribution start failed', (string) $assetFile->getId()),
                    exception: $e
                );

                throw new RuntimeException(message: $e->getMessage(), previous: $e);
            }

            return;
        }

        if ($status->in([AssetFileProcessStatus::Failed, AssetFileProcessStatus::Duplicate])) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_FILE_CHANGE_STATE,
                sprintf(
                    'VideoFile (%s) in status (%s), requested distributions (%s) not started',
                    (string) $assetFile->getId(),
                    $status->toString(),
                    implode(', ', $services)
                )
            );
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
