<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
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
     * @throws DomainException
     */
    public function getOriginImage(ImageFile $imageFile): ImageFile
    {
        $originImage = $this->findOriginImage($imageFile);
        if ($originImage) {
            return $originImage;
        }

        throw new DomainException(
            sprintf(
                'Get origin image failed. Image id (%s), status (%s), originImageId(%s)',
                $imageFile->getId(),
                $imageFile->getAssetAttributes()->getStatus()->toString(),
                $imageFile->getAssetAttributes()->getOriginAssetId()
            )
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
