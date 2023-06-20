<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;

final class ImageLinksManyTagsHandler extends ImageLinksHandler
{
    public function getImageLinkUrl(ImageFile $imageFile, array $tags): array
    {
        if ($imageFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            return [];
        }

        $res = [];
        foreach ($tags as $tag) {
            $links = [];
            foreach ($this->configurationProvider->getImageAdminSizeList($tag) as $allowItem) {
                $links[] = $this->serializeImageCrop($imageFile, $allowItem);
            }

            $res[$this->getKey($tag)] = $links;
        }

        return $res;
    }
}
