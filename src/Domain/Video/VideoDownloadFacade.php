<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusManager;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoMediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\CoreDamBundle\Traits\MessageBusAwareTrait;
use Closure;
use Doctrine\ORM\NonUniqueResultException;

final class VideoDownloadFacade
{
    use MessageBusAwareTrait;

    public function __construct(
        private readonly VideoFactory $videoFactory,
        private readonly AssetFactory $assetFactory,
        private readonly VideoManager $videoManager,
        private readonly ImagePreviewFactory $imagePreviewFactory,
        private readonly AssetFileStatusManager $assetFileStatusManager,
        private readonly VideoDistributionFacade $videoDistributionFacade,
        private readonly VideoFileRepository $videoFileRepository,
        private readonly DamLogger $damLogger,
    ) {
    }

    /**
     * @param ?Closure(AssetFile): void $setupData
     */
    public function createForDownload(
        AssetLicence $assetLicence,
        string $url,
        ?ImageFile $imagePreview = null,
        ?Closure $setupData = null,
    ): VideoFile {
        $videoFile = $this->videoFactory->createFromUrl($assetLicence, $url);
        $this->assetFactory->createForAssetFile($videoFile, $videoFile->getLicence());
        $this->videoManager->create($videoFile, flush: false);
        if ($setupData) {
            $setupData($videoFile);
        }
        if ($imagePreview) {
            $videoFile->setImagePreview($this->imagePreviewFactory->createFromImageFile($imagePreview, flush: false));
        }
        $this->assetFileStatusManager->toUploaded($videoFile);

        return $videoFile;
    }

    /**
     * @param list<string> $distributionServices
     *
     * @throws NonUniqueResultException
     */
    public function finishProcessing(VideoFile $videoFile, array $distributionServices): void
    {
        if (false === $videoFile->isCreatedByDownload()) {
            return;
        }

        match ($videoFile->getAssetAttributes()->getStatus()) {
            AssetFileProcessStatus::Processed => $this->handleProcessed($videoFile, $distributionServices),
            AssetFileProcessStatus::Failed => $this->handleFailed($videoFile),
            AssetFileProcessStatus::Duplicate => $this->handleDuplicate($videoFile, $distributionServices),
            default => null,
        };
    }

    /**
     * @param list<string> $distributionServices
     *
     * @throws NonUniqueResultException
     */
    private function handleProcessed(VideoFile $videoFile, array $distributionServices): void
    {
        if ($this->tryEnsureDistributed($videoFile, $distributionServices)) {
            return;
        }

        $this->messageBus->dispatch(VideoMediaStatusCallbackMessage::fromVideoFile(
            $videoFile,
            MediaStatusType::VideoFailed,
            VideoMediaStatusCallbackMessage::FAILURE_REASON_DISTRIBUTION_FAILED,
        ));
    }

    /**
     * @param list<string> $distributionServices
     *
     * @throws NonUniqueResultException
     */
    private function tryEnsureDistributed(VideoFile $videoFile, array $distributionServices): bool
    {
        try {
            $this->videoDistributionFacade->ensureDistributed($videoFile, $distributionServices);

            return true;
        } catch (ValidationException $exception) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_FILE_DOWNLOAD,
                sprintf('VideoFile (%s) distribution rejected', (string) $videoFile->getId()),
                exception: $exception
            );

            return false;
        }
    }

    private function handleFailed(VideoFile $videoFile): void
    {
        $this->messageBus->dispatch(VideoMediaStatusCallbackMessage::fromVideoFile(
            $videoFile,
            MediaStatusType::VideoFailed,
            $videoFile->getAssetAttributes()->getFailReason()->toString(),
        ));
    }

    /**
     * @param list<string> $distributionServices
     *
     * @throws NonUniqueResultException
     */
    private function handleDuplicate(VideoFile $videoFile, array $distributionServices): void
    {
        $originAssetFileId = $videoFile->getAssetAttributes()->getOriginAssetId();
        $origin = $this->videoFileRepository->find($originAssetFileId);
        if ($origin instanceof VideoFile && $origin->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)) {
            $this->tryEnsureDistributed($origin, $distributionServices);
        }

        $this->messageBus->dispatch(VideoMediaStatusCallbackMessage::fromVideoFile(
            $videoFile,
            MediaStatusType::VideoDuplicate,
            origin: $origin,
        ));
    }
}
