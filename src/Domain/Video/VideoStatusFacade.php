<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\FileProcessor\VideoAttributesProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\DuplicateAssetFileException;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;

/**
 * @method VideoFile finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile)
 */
final class VideoStatusFacade extends AbstractAssetFileStatusFacade
{
    public function __construct(
        private readonly VideoAttributesProcessor $attributesProcessor,
        private readonly VideoFileRepository $videoFileRepository,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return VideoFile::class;
    }

    /**
     * @throws FfmpegException
     */
    protected function processAssetFile(AssetFile $assetFile, File $file): AssetFile
    {
        $this->attributesProcessor->process($assetFile, $file);

        return $assetFile;
    }

    protected function checkDuplicate(AssetFile $assetFile): void
    {
        $originAsset = $this->videoFileRepository->findProcessedByChecksum($assetFile->getAssetAttributes()->getChecksum());
        if ($originAsset) {
            throw new DuplicateAssetFileException($originAsset, $assetFile);
        }
    }
}
