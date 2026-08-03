<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioManager;
use AnzuSystems\CoreDamBundle\Domain\Document\DocumentManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;

class AssetFileManagerProvider extends AbstractManager
{
    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly VideoManager $videoManager,
        private readonly DocumentManager $documentManager,
        private readonly AudioManager $audioManager,
    ) {
    }

    public function getManager(AssetFile $assetFile): AssetFileManager
    {
        return $this->getManagerByType($assetFile->getAssetType());
    }

    /**
     * @param iterable<AssetFile> $assetFiles
     *
     * @return array<string, bool> asset file id => can be removed
     */
    public function canBeRemovedBulk(iterable $assetFiles): array
    {
        $grouped = CollectionHelper::groupBy(
            $assetFiles,
            static fn (AssetFile $assetFile): string => $assetFile->getAssetType()->value,
        );

        $result = [];
        foreach ($grouped as $type => $assetFilesOfType) {
            $result += $this->getManagerByType(AssetType::from($type))->canBeRemovedBulk($assetFilesOfType);
        }

        return $result;
    }

    private function getManagerByType(AssetType $type): AssetFileManager
    {
        return match ($type) {
            AssetType::Image => $this->imageManager,
            AssetType::Video => $this->videoManager,
            AssetType::Audio => $this->audioManager,
            AssetType::Document => $this->documentManager,
        };
    }
}
