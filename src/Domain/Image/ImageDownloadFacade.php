<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;

final readonly class ImageDownloadFacade
{
    public function __construct(
        private ImageManager $imageManager,
        private ImageFactory $imageFactory,
        private ImageFileRepository $imageFileRepository,
        private AssetFileStatusManager $assetFileStatusManager,
        private AssetFactory $assetFactory,
        private AssetFileStatusFacadeProvider $facadeProvider,
        private OriginImageProvider $originImageProvider,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws AssetFileProcessFailed
     */
    public function downloadSynchronous(AssetLicence $assetLicence, string $url): ImageFile
    {
        $imageFile = $this->imageFileRepository->findOneProcessedByUrlAndLicence($url, $assetLicence);
        if ($imageFile) {
            return $imageFile;
        }

        $imageFile = $this->createImageFile($assetLicence, $url);
        $this->assetFileStatusManager->toUploaded($imageFile, false);
        $this->facadeProvider->getStatusFacade($imageFile)->storeAndProcess($imageFile);

        return $this->originImageProvider->getOriginImage($imageFile);
    }

    private function createImageFile(AssetLicence $assetLicence, string $url): ImageFile
    {
        $imageFile = $this->imageFactory->createFromUrl($assetLicence, $url);
        $this->assetFactory->createForAssetFile($imageFile, $imageFile->getLicence());
        $this->imageManager->create($imageFile, false);

        return $imageFile;
    }
}
