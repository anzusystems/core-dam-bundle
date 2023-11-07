<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\OriginImageProvider;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Domain\Video\FileProcessor\VideoAttributesProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Exception\DuplicateAssetFileException;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

/**
 * @method VideoFile finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile)
 */
final class VideoStatusFacade extends AbstractAssetFileStatusFacade
{
    private const THUMBNAIL_PERCENTAGE_POSITION = 0.1;

    public function __construct(
        private readonly VideoAttributesProcessor $attributesProcessor,
        private readonly VideoFileRepository $videoFileRepository,
        private readonly ImageFileRepository $imageFileRepository,
        private readonly FfmpegService $ffmpegService,
        private readonly ImageFactory $imageFactory,
        private readonly ImagePreviewFactory $imagePreviewFactory,
        private readonly OriginImageProvider $originImageProvider,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return VideoFile::class;
    }

    /**
     * @param VideoFile $assetFile
     *
     * @throws SerializerException|FfmpegException
     */
    protected function processAssetFile(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $this->attributesProcessor->process($assetFile, $file);

        try {
            $assetFile->setImagePreview(
                $this->imagePreviewFactory->createFromImageFile(
                    imageFile: $this->getPreviewImageFile($assetFile, $file),
                    flush: false
                )
            );
        } catch (FfmpegException|DomainException $exception) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_FILE_PROCESS,
                sprintf(
                    'Failed create preview image for video id (%s) with message (%s)',
                    $assetFile->getId(),
                    $exception->getMessage()
                )
            );
        }

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

    /**
     * @throws FfmpegException
     * @throws SerializerException
     * @throws DomainException
     */
    private function getPreviewImageFile(VideoFile $videoFile, AdapterFile $file): ImageFile
    {
        /** @var ImageFile $imageFile */
        $imageFile = $this->imageFactory->createAndProcessFromFile(
            file: $this->ffmpegService->getFileThumbnail($file, self::getThumbnailPosition($videoFile)),
            assetLicence: $videoFile->getLicence(),
            generatedBySystem: true
        );

        return $this->originImageProvider->getOriginImage($imageFile);
    }

    private function getThumbnailPosition(VideoFile $file): int
    {
        return (int) floor(
            $file->getAttributes()->getDuration() * self::THUMBNAIL_PERCENTAGE_POSITION
        );
    }
}
