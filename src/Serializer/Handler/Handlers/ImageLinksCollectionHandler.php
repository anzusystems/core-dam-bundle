<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;

final class ImageLinksCollectionHandler extends ImageLinksHandler
{
    public function getImageLinkUrl(ImageFile $imageFile, ImageCropTag $cropTag): array
    {
        if ($imageFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            return [];
        }

        $links = [];
        foreach ($this->configurationProvider->getImageAdminSizeList($cropTag->toString()) as $allowItem) {
            $links[] = $this->serializeImageCrop($imageFile, $allowItem);
        }

        return [
            $this->getKey($cropTag) => $links,
        ];
    }
}
