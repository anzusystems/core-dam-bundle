<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final readonly class FileAttributesProcessor
{
    public function __construct(
        private DamLogger $damLogger
    ) {
    }

    /**
     * @throws SerializerException
     * @throws AssetFileProcessFailed
     */
    public function process(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $checksum = FileHelper::checksumFromPath($file->getRealPath());

        if (false === (empty($assetFile->getAssetAttributes()->getChecksum())) &&
            false === ($checksum === $assetFile->getAssetAttributes()->getChecksum())
        ) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_CHANGE_STATE,
                sprintf(
                    'Checksum mismatch. Admin value (%s) vs BE value (%s)',
                    $assetFile->getAssetAttributes()->getChecksum(),
                    $checksum
                )
            );

            throw new AssetFileProcessFailed(AssetFileFailedType::InvalidChecksum);
        }

        $assetFile->getAssetAttributes()
            ->setMimeType((string) $file->getMimeType())
            ->setSize($file->getSize())
            ->setChecksum($checksum);

        return $assetFile;
    }
}
