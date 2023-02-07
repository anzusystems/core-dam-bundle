<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\ImagePreview;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoAdmUpdateDto;

final class VideoManager extends AssetFileManager
{
    public function __construct(
        private readonly ImagePreviewManager $imagePreviewManager,
        private readonly ImagePreviewFactory $imagePreviewFactory,
    ) {
    }

    public function update(VideoFile $video, VideoAdmUpdateDto $newVideo, bool $flush = true): VideoFile
    {
        $this->trackModification($video);
        $this->imagePreviewManager->setImagePreviewRelation($video, $newVideo);
        $this->flush($flush);

        return $video;
    }

    public function setImagePreview(VideoFile $video, ImageFile $file, bool $flush = true): ImagePreview
    {
        if (null === $video->getImagePreview()) {
            $video->setImagePreview(
                imagePreview: $this->imagePreviewFactory->createFromImageFile(
                    imageFile: $file,
                    flush: $flush
                )
            );
        }

        return $this->imagePreviewManager->updateExisting(
            imagPreview: $video->getImagePreview()
                ->setImageFile($file)
                ->setPosition(0),
            flush: $flush
        );
    }

    /**
     * @param VideoFile $assetFile
     */
    protected function deleteAssetFileRelations(AssetFile $assetFile): void
    {
    }
}
