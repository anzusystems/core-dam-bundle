<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Audio\FileProcessor\AudioAttributesProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Exception\DuplicateAssetFileException;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;
use InvalidArgumentException;

/**
 * @method AudioFile finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile)
 */
final class AudioStatusFacade extends AbstractAssetFileStatusFacade
{
    public function __construct(
        private readonly AudioAttributesProcessor $attributesProcessor,
        private readonly AudioFileRepository $audioFileRepository,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return AudioFile::class;
    }

    /**
     * @throws FfmpegException
     */
    protected function processAssetFile(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        if (false === ($assetFile instanceof AudioFile)) {
            throw new InvalidArgumentException('Asset type must be a type of audio');
        }

        $this->attributesProcessor->process($assetFile, $file);

        return $assetFile;
    }

    protected function checkDuplicate(AssetFile $assetFile): void
    {
        $originAsset = $this->audioFileRepository->findProcessedByChecksumAndLicence(
            checksum: $assetFile->getAssetAttributes()->getChecksum(),
            licence: $assetFile->getLicence(),
        );
        if ($originAsset) {
            throw new DuplicateAssetFileException($originAsset, $assetFile);
        }
    }
}
