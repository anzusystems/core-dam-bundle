<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Video\FileProcessor\VideoAttributesProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\DuplicateAssetFileException;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;

/**
 * @method VideoFile finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile)
 */
final class VideoStatusFacade extends AbstractAssetFileStatusFacade
{
    private const THUMBNAIL_PERCENTAGE_POSITION = 0.1;

    public function __construct(
        private readonly VideoAttributesProcessor $attributesProcessor,
        private readonly VideoFileRepository $videoFileRepository,
        private readonly FfmpegService $ffmpegService,
        private readonly ImageFactory $imageFactory,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return VideoFile::class;
    }

    /**
     * @param VideoFile $assetFile
     *
     * @throws FfmpegException
     */
    protected function processAssetFile(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $this->attributesProcessor->process($assetFile, $file);

        $imageFile = $this->imageFactory->createAndProcessFromFile(
            file: $this->ffmpegService->getFileThumbnail($file, self::getThumbnailPosition($assetFile)),
            assetLicence: $assetFile->getLicence()
        );

        $assetFile->setPreviewImage($imageFile->getAsset());

        return $assetFile;
    }

    protected function checkDuplicate(AssetFile $assetFile): void
    {
        $originAsset = $this->videoFileRepository->findProcessedByChecksumAndLicence(
            checksum: $assetFile->getAssetAttributes()->getChecksum(),
            licence: $assetFile->getLicence(),
        );
        if ($originAsset) {
            throw new DuplicateAssetFileException($originAsset, $assetFile);
        }
    }

    private function getThumbnailPosition(VideoFile $file): int
    {
        return (int) floor(
            $file->getAttributes()->getDuration() * self::THUMBNAIL_PERCENTAGE_POSITION
        );
    }
}
