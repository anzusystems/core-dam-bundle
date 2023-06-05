<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\FileSystem\MimeGuesser;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final class FileAttributesProcessor
{
    use FileHelperTrait;

    public function __construct(
        private readonly DamLogger $damLogger
    ) {
    }

    /**
     * @throws SerializerException
     * @throws AssetFileProcessFailed
     */
    public function process(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $checksum = MimeGuesser::checksumFromPath($file->getRealPath());

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
            ->setMimeType(
                $this->fileHelper->guessMime(
                    path: (string) $file->getRealPath(),
                    useFfmpeg: true
                )
            )
            ->setSize($file->getSize())
            ->setChecksum($checksum);

        return $assetFile;
    }
}
