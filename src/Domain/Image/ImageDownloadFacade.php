<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileMessageDispatcher;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use RuntimeException;

final class ImageDownloadFacade
{
    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly ImageFactory $imageFactory,
        private readonly ImageFileRepository $imageFileRepository,
        private readonly AssetFileStatusManager $assetFileStatusManager,
        private readonly AssetFileEventDispatcher $assetFileEventDispatcher,
        private readonly AssetFileMessageDispatcher $messageDispatcher,
        private readonly AssetFactory $assetFactory,
    ) {
    }

    /**
     * @throws RuntimeException
     * @throws SerializerException
     */
    public function download(AssetLicence $assetLicence, string $url): ImageFile
    {
        $imageFile = $this->imageFileRepository->findOneByUrlAndLicence(
            url: $url,
            licence: $assetLicence
        );

        if ($imageFile) {
            return $imageFile;
        }

        $imageFile = $this->imageFactory->createFromUrl($assetLicence, $url);
        $this->assetFactory->createForAssetFile($imageFile, $imageFile->getLicence());
        $this->imageManager->create($imageFile, false);

        $this->assetFileStatusManager->toUploaded($imageFile);
        $this->assetFileEventDispatcher->dispatchAssetFileChanged($imageFile);
        $this->messageDispatcher->dispatchAssetFileChangeState($imageFile);

        return $imageFile;
    }
}
