<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\FileSystem\MimeGuesser;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\DocumentMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\VideoMimeTypes;
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
    public function processChecksum(AssetFile $assetFile, AdapterFile $file): AssetFile
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
            ->setChecksum($checksum);

        return $assetFile;
    }

    /**
     * @throws SerializerException
     * @throws AssetFileProcessFailed
     */
    public function processAttributes(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $assetType = $assetFile->getAsset()->getAttributes()->getAssetType();
        // At this moment, ffmpeg guesser is used only for Audio m4a type
        $mimeType = $this->fileHelper->guessMime(
            path: (string) $file->getRealPath(),
            useFfmpeg: $assetType->is(AssetType::Audio)
        );

        if (false === $this->supportsMimeType($assetType, $mimeType)) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_CHANGE_STATE,
                sprintf(
                    'Invalid mime (%s) for type (%s). AssetFileId (%s)',
                    $mimeType,
                    $assetType->toString(),
                    $assetFile->getId()
                )
            );

            throw new AssetFileProcessFailed(AssetFileFailedType::InvalidMimeType);
        }

        dump($mimeType);

        $assetFile->getAssetAttributes()
            ->setMimeType($mimeType)
            ->setSize($file->getSize());

        return $assetFile;
    }

    protected function supportsMimeType(AssetType $assetType, string $mimeType): bool
    {
        return match ($assetType) {
            AssetType::Image => in_array($mimeType, ImageMimeTypes::CHOICES, true),
            AssetType::Video => in_array($mimeType, VideoMimeTypes::CHOICES, true),
            AssetType::Audio => in_array($mimeType, AudioMimeTypes::CHOICES, true),
            AssetType::Document => in_array($mimeType, DocumentMimeTypes::CHOICES, true),
        };
    }
}
