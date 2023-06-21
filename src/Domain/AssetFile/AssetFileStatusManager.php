<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

/**
 * @extends AssetFileManager<AssetFile>
 */
final class AssetFileStatusManager extends AssetFileManager
{
    private DamLogger $damLogger;

    #[Required]
    public function setDamLogger(DamLogger $damLogger): void
    {
        $this->damLogger = $damLogger;
    }

    /**
     * @throws SerializerException
     */
    public function toUploaded(AssetFile $assetFile, bool $flush = true): AssetFile
    {
        return $this->changeTransition(
            assetFile: $assetFile,
            status: AssetFileProcessStatus::Uploaded,
            flush: $flush
        );
    }

    /**
     * @throws SerializerException
     */
    public function toStored(AssetFile $assetFile): AssetFile
    {
        return $this->changeTransition($assetFile, AssetFileProcessStatus::Stored);
    }

    /**
     * @throws SerializerException
     */
    public function toDuplicate(AssetFile $assetFile): AssetFile
    {
        return $this->changeTransition($assetFile, AssetFileProcessStatus::Duplicate);
    }

    /**
     * @throws SerializerException
     */
    public function toProcessed(AssetFile $assetFile): AssetFile
    {
        return $this->changeTransition($assetFile, AssetFileProcessStatus::Processed);
    }

    /**
     * @throws SerializerException
     */
    public function toFailed(AssetFile $assetFile, AssetFileFailedType $failedType, Throwable $throwable): AssetFile
    {
        $this->changeTransition($assetFile, AssetFileProcessStatus::Failed, $failedType);

        $this->damLogger->error(
            namespace: DamLogger::NAMESPACE_ASSET_FILE_PROCESS,
            message: sprintf(
                'Asset file (%s) process failed reason (%s). (%s',
                (string) $assetFile->getId(),
                $failedType->toString(),
                $throwable->getMessage()
            ),
            exception: $throwable
        );

        return $assetFile;
    }

    /**
     * @throws SerializerException
     */
    private function changeTransition(
        AssetFile $assetFile,
        AssetFileProcessStatus $status,
        ?AssetFileFailedType $failedType = null,
        bool $flush = true
    ): AssetFile {
        $this->validateTransition($assetFile, $status);
        $assetFile->getAssetAttributes()
            ->setStatus($status)
            ->setFailReason($failedType ?? AssetFileFailedType::None)
        ;

        return $this->updateExisting($assetFile);
    }

    /**
     * @throws SerializerException
     * @throws ForbiddenOperationException
     */
    private function validateTransition(AssetFile $assetFile, AssetFileProcessStatus $status): void
    {
        $allowedStatuses = match ($assetFile->getAssetAttributes()->getStatus()) {
            AssetFileProcessStatus::Uploading => [AssetFileProcessStatus::Failed, AssetFileProcessStatus::Uploaded],
            AssetFileProcessStatus::Uploaded => [AssetFileProcessStatus::Failed, AssetFileProcessStatus::Stored, AssetFileProcessStatus::Duplicate],
            AssetFileProcessStatus::Stored => [AssetFileProcessStatus::Failed, AssetFileProcessStatus::Processed, AssetFileProcessStatus::Duplicate],
            AssetFileProcessStatus::Duplicate => [AssetFileProcessStatus::Failed],
            AssetFileProcessStatus::Processed => [AssetFileProcessStatus::Failed],
            AssetFileProcessStatus::Failed => [],
        };

        if (in_array($status, $allowedStatuses, true)) {
            return;
        }

        $this->damLogger->error(
            'AssetFileProcess',
            sprintf(
                'Asset file (%s) failed transition from (%s) to (%s)',
                (string) $assetFile->getId(),
                $assetFile->getAssetAttributes()->getStatus()->toString(),
                $status->toString(),
            )
        );

        throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_INVALID_STATE_TRANSACTION);
    }
}
