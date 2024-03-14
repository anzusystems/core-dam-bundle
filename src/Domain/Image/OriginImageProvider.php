<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;

final readonly class OriginImageProvider
{
    public function __construct(
        private ImageFileRepository $imageFileRepository,
    ) {
    }

    /**
     * @throws AssetFileProcessFailed
     */
    public function getOriginImage(ImageFile $imageFile): ImageFile
    {
        $originImage = $this->findOriginImage($imageFile);
        if ($originImage) {
            return $originImage;
        }

        //        dump(
        //            $imageFile,
        //            $imageFile->getAssetAttributes()->getStatus(),
        //            $imageFile->getAssetAttributes()->getFailReason()
        //        );

        throw new AssetFileProcessFailed(
            $imageFile->getAssetAttributes()->getFailReason()
        );
    }

    public function findOriginImage(ImageFile $imageFile): ?ImageFile
    {
        if ($imageFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)) {
            return $imageFile;
        }

        if (
            $imageFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Duplicate) &&
            false === empty($imageFile->getAssetAttributes()->getOriginAssetId())
        ) {
            return $this->imageFileRepository->find($imageFile->getAssetAttributes()->getOriginAssetId());
        }

        return null;
    }
}
