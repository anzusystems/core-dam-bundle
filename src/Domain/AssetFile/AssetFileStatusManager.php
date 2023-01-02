<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;

final class AssetFileStatusManager extends AssetFileManager
{
    public function toUploaded(AssetFile $assetFile): AssetFile
    {
        $this->validateTransition($assetFile, AssetFileProcessStatus::Uploaded);
        $assetFile->getAssetAttributes()
            ->setStatus(AssetFileProcessStatus::Uploaded);

        return $this->updateExisting($assetFile);
    }

    public function toStored(AssetFile $assetFile): AssetFile
    {
        $this->validateTransition($assetFile, AssetFileProcessStatus::Stored);
        $assetFile->getAssetAttributes()
            ->setStatus(AssetFileProcessStatus::Stored);

        return $this->updateExisting($assetFile);
    }

    public function toDuplicate(AssetFile $assetFile): AssetFile
    {
        $this->validateTransition($assetFile, AssetFileProcessStatus::Duplicate);
        $assetFile->getAssetAttributes()
            ->setStatus(AssetFileProcessStatus::Duplicate);

        return $this->updateExisting($assetFile);
    }

    public function toStoring(AssetFile $assetFile): AssetFile
    {
        $this->validateTransition($assetFile, AssetFileProcessStatus::Storing);
        $assetFile->getAssetAttributes()
            ->setStatus(AssetFileProcessStatus::Storing);

        return $this->updateExisting($assetFile);
    }

    public function toProcessing(AssetFile $assetFile): AssetFile
    {
        $this->validateTransition($assetFile, AssetFileProcessStatus::Processing);
        $assetFile->getAssetAttributes()
            ->setStatus(AssetFileProcessStatus::Processing);

        return $this->updateExisting($assetFile);
    }

    public function toProcessed(AssetFile $assetFile, bool $flush = true): AssetFile
    {
        $this->validateTransition($assetFile, AssetFileProcessStatus::Processed);
        $assetFile->getAssetAttributes()
            ->setStatus(AssetFileProcessStatus::Processed);

        return $this->updateExisting($assetFile, $flush);
    }

    public function toFailed(AssetFile $assetFile, AssetFileFailedType $failedType): AssetFile
    {
        $this->validateTransition($assetFile, AssetFileProcessStatus::Failed);
        $assetFile->getAssetAttributes()
            ->setStatus(AssetFileProcessStatus::Failed)
            ->setFailReason($failedType);

        return $this->updateExisting($assetFile);
    }

    /**
     * @throws ForbiddenOperationException
     */
    private function validateTransition(AssetFile $assetFile, AssetFileProcessStatus $status): void
    {
        $allowedStatuses = match ($assetFile->getAssetAttributes()->getStatus()) {
            AssetFileProcessStatus::Uploading => [AssetFileProcessStatus::Failed, AssetFileProcessStatus::Uploaded],
            AssetFileProcessStatus::Uploaded => [AssetFileProcessStatus::Failed, AssetFileProcessStatus::Storing],
            AssetFileProcessStatus::Storing => [AssetFileProcessStatus::Stored],
            AssetFileProcessStatus::Stored => [AssetFileProcessStatus::Failed, AssetFileProcessStatus::Duplicate, AssetFileProcessStatus::Processing],
            AssetFileProcessStatus::Duplicate => [AssetFileProcessStatus::Failed],
            AssetFileProcessStatus::Processing => [AssetFileProcessStatus::Failed, AssetFileProcessStatus::Stored, AssetFileProcessStatus::Processed],
            AssetFileProcessStatus::Processed => [AssetFileProcessStatus::Failed],
            AssetFileProcessStatus::Failed => [],
        };

        if (in_array($status, $allowedStatuses, true)) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_STATE_TRANSACTION);
    }
}
