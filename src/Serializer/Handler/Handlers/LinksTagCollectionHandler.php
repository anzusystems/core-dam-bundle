<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\SerializerBundle\Helper\SerializerHelper;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

class LinksTagCollectionHandler extends LinksHandler
{
    public function describe(string $property, Metadata $metadata): array
    {
        $description = [
            'property' => $property,
            'type' => SerializerHelper::getOaFriendlyType($metadata->type),
        ];

        return $description;
    }

    protected function getImageFileLinks(ImageFile $imageFile, Metadata $metadata): array
    {
        if ($imageFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            return [];
        }

        $res = [];
        $tags = is_string($metadata->customType) ? [$metadata->customType] : $this->getTagsFromRequest(self::IMAGE_TAGS);
        foreach ($tags as $tag) {
            $links = [];
            $sizeList = $this->getTaggedList($imageFile, $tag);

            foreach ($sizeList as $allowItem) {
                $links[] = $this->serializeImageCrop($imageFile, $allowItem);
            }

            $res[$this->getKey($tag)] = $links;
        }

        return is_string($metadata->customType)
            ? $res[$this->getKey($metadata->customType)] ?? []
            : $res
        ;
    }
}
