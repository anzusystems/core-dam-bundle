<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Audio\FileProcessor\AudioAttributesProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Exception\DuplicateAssetFileException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;

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

    protected function processAssetFile(AssetFile $assetFile, File $file): AssetFile
    {
        $this->attributesProcessor->process($assetFile, $file);

        return $assetFile;
    }

    protected function checkDuplicate(AssetFile $assetFile): void
    {
        $originAsset = $this->audioFileRepository->findProcessedByChecksum($assetFile->getAssetAttributes()->getChecksum());
        if ($originAsset) {
            throw new DuplicateAssetFileException($originAsset, $assetFile);
        }
    }
}
