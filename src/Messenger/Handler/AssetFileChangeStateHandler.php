<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\AbstractAssetFileMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\AudioFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\DocumentFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\ImageFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class AssetFileChangeStateHandler
{
    public function __construct(
        private AssetFileRepository $assetFileRepository,
        private AssetFileStatusFacadeProvider $facadeProvider,
        private DamLogger $damLogger,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws RuntimeException
     */
    #[AsMessageHandler]
    public function handleVideoFile(VideoFileChangeStateMessage $message): void
    {
        $this->handleAssetFile($message);
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
     * @throws SerializerException
     * @throws RuntimeException
     */
    private function handleAssetFile(AbstractAssetFileMessage $message): void
    {
        $assetFile = $this->assetFileRepository->find($message->getAssetId());

        if (null === $assetFile) {
            return;
        }

        try {
            match ($assetFile->getAssetAttributes()->getStatus()) {
                AssetFileProcessStatus::Uploaded => $this->facadeProvider->getStatusFacade($assetFile)->storeAndProcess($assetFile),
                default => $this->damLogger->info(
                    DamLogger::NAMESPACE_ASSET_CHANGE_STATE,
                    sprintf(
                        'AssetFile (%s) change state to (%s) not suitable for handle',
                        $assetFile->getId(),
                        $assetFile->getAssetAttributes()->getStatus()->toString()
                    ),
                )
            };
        } catch (Throwable $e) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_FILE_CHANGE_STATE,
                sprintf(
                    'AssetFile (%s) change state to (%s) failed',
                    $assetFile->getId(),
                    $assetFile->getAssetAttributes()->getStatus()->toString()
                ),
                $e
            );

            throw new RuntimeException(message: $e->getMessage(), previous: $e);
        }
    }
}
