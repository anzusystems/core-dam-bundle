<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusManager;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use Closure;

final readonly class VideoDownloadFacade
{
    public function __construct(
        private VideoFactory $videoFactory,
        private AssetFactory $assetFactory,
        private VideoManager $videoManager,
        private ImagePreviewFactory $imagePreviewFactory,
        private AssetFileStatusManager $assetFileStatusManager,
    ) {
    }

    /**
     * @param ?Closure(AssetFile): void $setupData
     */
    public function createForDownload(
        AssetLicence $assetLicence,
        string $url,
        ?ImageFile $imagePreview = null,
        ?Closure $setupData = null,
    ): VideoFile {
        $videoFile = $this->videoFactory->createFromUrl($assetLicence, $url);
        $this->assetFactory->createForAssetFile($videoFile, $videoFile->getLicence());
        $this->videoManager->create($videoFile, flush: false);
        if ($setupData) {
            $setupData($videoFile);
        }
        if ($imagePreview) {
            $videoFile->setImagePreview($this->imagePreviewFactory->createFromImageFile($imagePreview, flush: false));
        }
        $this->assetFileStatusManager->toUploaded($videoFile);

        return $videoFile;
    }
}
