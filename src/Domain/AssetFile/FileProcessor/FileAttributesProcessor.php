<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;

final class FileAttributesProcessor
{
    public function __construct(
        private readonly DamLogger $damLogger
    ) {
    }

    public function process(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $checksum = FileHelper::checksumFromPath($file->getRealPath());

        if (false === (empty($assetFile->getAssetAttributes()->getChecksum())) &&
            false === ($checksum === $assetFile->getAssetAttributes()->getChecksum())
        ) {
            // todo throw exception invalid checksum
            $this->damLogger->warning(
                DamLogger::NAMESPACE_ASSET_CHANGE_STATE,
                sprintf(
                    'Checksum mismatch. Admin value (%s) vs BE value (%s)',
                    $assetFile->getAssetAttributes()->getChecksum(),
                    $checksum
                )
            );
        }

        $assetFile->getAssetAttributes()
            ->setMimeType((string) $file->getMimeType())
            ->setSize($file->getSize())
            ->setChecksum($checksum);

        return $assetFile;
    }
}
